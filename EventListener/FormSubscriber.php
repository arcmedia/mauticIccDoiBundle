<?php

namespace MauticPlugin\IccDoiBundle\EventListener;

use Mautic\FormBundle\Event\FormBuilderEvent;
use Mautic\FormBundle\Event\SubmissionEvent;
use Mautic\FormBundle\FormEvents;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use MauticPlugin\IccDoiBundle\Form\Type\FormSubmitActionSendDoi;
use MauticPlugin\IccDoiBundle\Form\Type\FormSubmitActionSetOptedOut;
use MauticPlugin\IccDoiBundle\Helper\DoiStatusHelper;
use MauticPlugin\IccDoiBundle\IccDoiStatus;
use MauticPlugin\IccDoiBundle\Integration\IccDoiIntegration;
use MauticPlugin\IccDoiBundle\Form\Type\FormSubmitActionSetLanguage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FormSubscriber implements EventSubscriberInterface
{
    /**
     * @var LeadModel
     */
    private $leadModel;

    /**
     * @var IccDoiIntegration
     */
    private $integration;

    /**
     * @var DoiStatusHelper
     */
    private $doiStatusHelper;

    public function __construct(IntegrationHelper $integrationHelper, LeadModel $leadModel, DoiStatusHelper $doiStatusHelper)
    {
        $this->integration = $integrationHelper->getIntegrationObject('IccDoi');
        $this->leadModel = $leadModel;
        $this->doiStatusHelper = $doiStatusHelper;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            FormEvents::FORM_ON_BUILD => ['onFormBuilder', 0],
            FormEvents::ON_EXECUTE_SUBMIT_ACTION => [
                ['sendDoi', 0],
                ['setOptedOut', 0],
            ],
        ];
    }

    /**
     * Add a lead generation action to available form submit actions.
     * @throws \Mautic\CoreBundle\Exception\BadConfigurationException
     */
    public function onFormBuilder(FormBuilderEvent $event)
    {
        $event->addSubmitAction('lead.senddoi', [
            'group' => 'mautic.lead.lead.submitaction',
            'label' => 'Check DOI-Status',
            'description' => 'Send Doi Mail',
            'formType' => FormSubmitActionSendDoi::class,
            'formTheme' => '@IccDoi/FormTheme/FormSubmitActionSendDoi.html.twig',
            'eventName' => FormEvents::ON_EXECUTE_SUBMIT_ACTION,
            'allowCampaignForm' => true,
        ]);

        $event->addSubmitAction('lead.setoptout', [
            'group' => 'mautic.lead.lead.submitaction',
            'label' => 'Set Opted-Out Status',
            'description' => 'Set Opted-Out Status',
            'formType' => FormSubmitActionSetOptedOut::class,
            'formTheme' => '@IccDoi/FormTheme/FormSubmitActionSetOptedOut.html.twig',
            'eventName' => FormEvents::ON_EXECUTE_SUBMIT_ACTION,
            'allowCampaignForm' => true,
        ]);
    }

    public function sendDoi(SubmissionEvent $event): void
    {
        if (false === $event->checkContext('lead.senddoi')) {
            return;
        }

        if (!$this->integration->getIntegrationSettings()->getIsPublished())
            return;

        if (!($lead = $event->getLead()) || !($email = $event->getLead()->getEmail()))
            return;

        if ($this->doiStatusHelper->getCurrentDoiStatus($lead) !== IccDoiStatus::DOI_CONFIRMED) {
            if ($this->doiStatusHelper->sendDoiEmail($lead)) {
                $this->doiStatusHelper->setDoiStatus($lead, IccDoiStatus::DOI_PENDING);

            }
        }
    }

    public function setOptedOut(SubmissionEvent $event): void
    {
        if (false === $event->checkContext('lead.setoptout')) {
            return;
        }

        if (!$this->integration->getIntegrationSettings()->getIsPublished())
            return;

        if (!($lead = $event->getLead()) || !($email = $event->getLead()->getEmail()))
            return;

        if ($this->doiStatusHelper->getCurrentDoiStatus($lead) === IccDoiStatus::DOI_CONFIRMED)
            $this->doiStatusHelper->setDoiStatus($lead, IccDoiStatus::DOI_OPTED_OUT);
    }
}
