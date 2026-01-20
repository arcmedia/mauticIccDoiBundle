<?php


namespace MauticPlugin\IccDoiBundle\EventListener;

use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use Mautic\CampaignBundle\Event\DecisionEvent;
use Mautic\CampaignBundle\Executioner\Dispatcher\Exception\LogNotProcessedException;
use Mautic\CampaignBundle\Executioner\Dispatcher\Exception\LogPassedAndFailedException;
use Mautic\CampaignBundle\Executioner\Exception\CannotProcessEventException;
use Mautic\CampaignBundle\Executioner\RealTimeExecutioner;
use Mautic\CampaignBundle\Executioner\Scheduler\Exception\NotSchedulableException;
use Mautic\EmailBundle\Entity\Stat;
use Mautic\EmailBundle\Entity\StatRepository;
use Mautic\FormBundle\Event\SubmissionEvent;
use Mautic\FormBundle\FormEvents;
use Mautic\LeadBundle\Event\ContactIdentificationEvent;
use Mautic\LeadBundle\Exception\ImportFailedException;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\LeadBundle\Tracker\ContactTracker;
use Mautic\PageBundle\Model\RedirectModel;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use MauticPlugin\IccDoiBundle\Event\DoiChangeEvent;
use MauticPlugin\IccDoiBundle\Form\Type\CampaignCheckDoiStatusType;
use MauticPlugin\IccDoiBundle\Helper\DoiStatusHelper;
use MauticPlugin\IccDoiBundle\IccDoiEvents;
use MauticPlugin\IccDoiBundle\IccDoiStatus;
use MauticPlugin\IccDoiBundle\Integration\IccDoiIntegration;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class FormSubscriber
 */
class DoiSubscriber implements EventSubscriberInterface
{
    /**
     * @var IccDoiIntegration
     */
    private $integration;

    /**
     * @var DoiStatusHelper
     */
    private $doiStatusHelper;

    /**
     * @var RealTimeExecutioner
     */
    private $realTimeExecutioner;

    /**
     * @var StatRepository
     */
    private $statRepository;

    /**
     * @var RedirectModel
     */
    private $redirectModel;

    /**
     * @var LeadModel
     */
    private $leadModel;

    /**
     * @var ContactTracker
     */
    private $contactTracker;

    public function __construct(IntegrationHelper $integrationHelper,
                                DoiStatusHelper $doiStatusHelper,
                                RealTimeExecutioner $realTimeExecutioner,
                                StatRepository $statRepository,
                                RedirectModel $redirectModel,
                                LeadModel $leadModel,
                                ContactTracker $contactTracker
    )
    {
        $this->integration = $integrationHelper->getIntegrationObject('IccDoi');
        $this->doiStatusHelper = $doiStatusHelper;
        $this->realTimeExecutioner = $realTimeExecutioner;
        $this->statRepository = $statRepository;
        $this->redirectModel = $redirectModel;
        $this->leadModel = $leadModel;
        $this->contactTracker = $contactTracker;
    }

    /**
     * {@inheritdoc}
     */
    static public function getSubscribedEvents()
    {
        return array(
            LeadEvents::ON_CLICKTHROUGH_IDENTIFICATION => ['onClickthroughIdentification', 255],
            CampaignEvents::CAMPAIGN_ON_BUILD => ['onCampaignBuild', 0],
            CampaignEvents::ON_EVENT_DECISION_EVALUATION => ['onCheckDoiTriggerDecision', 0],
            IccDoiEvents::ON_DOI_STATUS_CHANGE => ['onDoiStatusChange', 0],
        );
    }

    /**
     * @param ContactIdentificationEvent $event
     */
    public function onClickthroughIdentification(ContactIdentificationEvent $event)
    {
        if (!$this->integration->getIntegrationSettings()->getIsPublished())
            return;

        if (!($clickthrough = $event->getClickthrough()))
            return;

        /** @var Stat $stat */
        $stat = $this->statRepository->findOneBy(['trackingHash' => $clickthrough['stat']]);

        if (!$stat) {
            return;
        }

        if (!$lead = $stat->getLead()) {
            return;
        }

        $uri = $_SERVER['REQUEST_URI'] ?? null;
        $query = $_SERVER['QUERY_STRING'] ?? '';

        if (!$uri) {
            return;
        }

        $redirectId = str_replace('?' . $query, '', $uri);
        $redirectId = str_replace('/r/', '', $redirectId);
        $redirect = $this->redirectModel->getRedirectById($redirectId);

        if (!$redirect)
            return;

        $isDoiActivation = $this->doiStatusHelper->isDoiTargetPage($lead, $redirect->getUrl());
        $isUnsubscribe   = $this->doiStatusHelper->isDoiUnsubscribePage($lead, $redirect->getUrl());

        if ($isDoiActivation)
            $this->doiStatusHelper->setDoiStatus($lead, IccDoiStatus::DOI_CONFIRMED);
        if ($isUnsubscribe)
            $this->doiStatusHelper->setDoiStatus($lead, IccDoiStatus::DOI_OPTED_OUT);
    }

    /**
     * Add event triggers and actions.
     * @param CampaignBuilderEvent $event
     */
    public function onCampaignBuild(CampaignBuilderEvent $event)
    {
        if (!$this->integration->getIntegrationSettings()->getIsPublished())
            return;

        $event->addDecision('icc.checkdoistatus',
            [
                'label' => 'Wait for DOI',
                'description' => 'Checks Doi Status of Lead',
                'formType' => CampaignCheckDoiStatusType::class,
                'eventName' => IccDoiEvents::ON_CHECK_DOI_TRIGGER_DECISION,
            ]
        );
    }

    /**
     * Campaign Decision that checks the Doi Status of a Lead
     *
     * @param DecisionEvent $event
     * @return bool|CampaignExecutionEvent
     */
    public function onCheckDoiTriggerDecision(DecisionEvent $event)
    {
        if (!$this->integration->getIntegrationSettings()->getIsPublished())
            return true;

        if (!$event->checkContext('icc.checkdoistatus')) {
            return false;
        }

        $result = $this->doiStatusHelper->getCurrentDoiStatus($event->getLead()) === IccDoiStatus::DOI_CONFIRMED;

        return $event->setResult($result);
    }

    /**
     * Gets fired if the Doi Status of a Lead changes
     *
     * @param DoiChangeEvent $event
     * @throws CannotProcessEventException
     * @throws ImportFailedException
     * @throws LogNotProcessedException
     * @throws LogPassedAndFailedException
     * @throws NotSchedulableException
     */
    public function onDoiStatusChange(DoiChangeEvent $event)
    {
        if (!$this->integration->getIntegrationSettings()->getIsPublished())
            return;

        if (!($lead = $event->getLead()))
            return;

        $dateTime = new \DateTime('now');
        $now = $dateTime->format('Y-m-d H:i:s');

        $data = [
            'icc_doi_status' => [
                'value' => $event->getDoiStatus(),
            ]
        ];

        if ($event->getDoiStatus() === IccDoiStatus::DOI_CONFIRMED)
            $data['icc_doi_accepted_date'] = $now;
        if ($event->getDoiStatus() === IccDoiStatus::DOI_OPTED_OUT)
            $data['icc_doi_declined_date'] = $now;

        $this->leadModel->setFieldValues($lead, $data);
        $this->leadModel->save($lead);

        $this->contactTracker->setTrackedContact($lead);

        if ($event->getDoiStatus() !== IccDoiStatus::DOI_PENDING)
            $this->realTimeExecutioner->execute('icc.checkdoistatus', ['lead' => $lead]);
    }
}