<?php

namespace MauticPlugin\IccDoiBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Event\EmailBuilderEvent;
use Mautic\EmailBundle\Event\EmailSendEvent;
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\PageBundle\Entity\Redirect;
use Mautic\PageBundle\Model\RedirectModel;
use Mautic\PageBundle\Model\TrackableModel;
use MauticPlugin\IccDoiBundle\Helper\DoiStatusHelper;
use MauticPlugin\IccDoiBundle\IccDoiUrls;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;


class BuilderSubscriber implements EventSubscriberInterface
{
    /**
     * @var CoreParametersHelper
     */
    private $coreParametersHelper;

    /**
     * @var EmailModel
     */
    private $emailModel;

    /**
     * @var TrackableModel
     */
    private $pageTrackableModel;

    /**
     * @var RedirectModel
     */
    private $pageRedirectModel;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var DoiStatusHelper
     */
    private $doiStatusHelper;

    public function __construct(
        CoreParametersHelper $coreParametersHelper,
        EmailModel $emailModel,
        TrackableModel $trackableModel,
        RedirectModel $redirectModel,
        TranslatorInterface $translator,
        EntityManagerInterface $entityManager,
        DoiStatusHelper $doiStatusHelper,
    ) {
        $this->coreParametersHelper = $coreParametersHelper;
        $this->emailModel           = $emailModel;
        $this->pageTrackableModel   = $trackableModel;
        $this->pageRedirectModel    = $redirectModel;
        $this->translator           = $translator;
        $this->entityManager        = $entityManager;
        $this->doiStatusHelper      = $doiStatusHelper;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            EmailEvents::EMAIL_ON_BUILD => ['onEmailBuild', 0],
            EmailEvents::EMAIL_ON_SEND  => ['onEmailGenerate', 0],

        ];
    }

    public function onEmailBuild(EmailBuilderEvent $event)
    {
        $tokens = [
            '{doi_activation}' => "DOI-Activation Link",
            '{doi_unsubscribe_url}' => "DOI-Unsubscribe Link",
            '{doi_unsubscribe_form_url}' => "DOI-Unsubscribe Form Link",

            // --- NEW TOKENS FOR DOI TYPE EMAIL ---
            '{doi_title}'                 => 'Lead Title',
            '{doi_lastname}'              => 'Lead Lastname',
            '{doi_company_name}'          => 'Lead Company',
            '{doi_email}'                 => 'Lead Email',
            '{doi_preferred_locale}'      => 'Lead Preferred Locale',
            '{doi_opt_in_date}'           => 'DOI Accepted Date',

            '{doi_email_type_prices}'     => 'DOI Type: Prices Link',
            '{doi_email_type_press}'      => 'DOI Type: Press Link',
            '{doi_email_type_public}'     => 'DOI Type: Public Link',
        ];

        if ($event->tokensRequested(array_keys($tokens))) {
            $event->addTokens(
                $event->filterTokens($tokens)
            );
        }
    }

    public function onEmailGenerate(EmailSendEvent $event)
    {
        $email = $event->getEmail();
        $lead = $event->getLead();

        if ((int)$this->doiStatusHelper->getFeatureSetting('doi_email_type_mail') === $email->getId()) {
            $this->onTypeMailGenerate($event);
            return;
        }

        if (is_array($lead) && $lead['id'] === 0)
            return;

        if ($this->doiStatusHelper->isDoiEmail($email)) {
            $activationLink = $this->doiStatusHelper->buildActivationLink($lead);
            $event->addToken('{doi_activation}', $activationLink);
        }
        $unsubscribeLink = $this->doiStatusHelper->buildUnsubscribeLink($lead);
        $unsubscribeFormLink = $this->doiStatusHelper->buildUnsubscribeFormLink($lead);
        $event->addToken('{doi_unsubscribe_url}', $unsubscribeLink);
        $event->addToken('{doi_unsubscribe_form_url}', $unsubscribeFormLink);
    }

    /**
     * onTypeMailGenerate: replace Tokens and create links on TypeMail generate
     * insignio HG 04.08.23
     * @param EmailSendEvent $event
     * @return void
     */
    protected function onTypeMailGenerate(EmailSendEvent $event): void {
        $lead = $event->getLead();

        if ($lead == null) return;

        $linkUrl = rtrim($this->coreParametersHelper->get('site_url'), '/')
            . IccDoiUrls::SET_EMAIL_TYPE_ROUTE;
        $jsonMeta = $this->doiStatusHelper->buildTypeMeta($lead);
        $leadId = is_array($lead) ? $lead['id'] : $lead->getId();
        $leadObject = $this->doiStatusHelper->getLeadEntityByID($leadId);

        $replaceTokens = [
            'doi_email_type_prices',
            'doi_email_type_press',
            'doi_email_type_public',
        ];

        $event->addToken('{doi_title}', $leadObject->getTitle());
        $event->addToken('{doi_lastname}', $leadObject->getLastname());
        $event->addToken('{doi_company_name}', $leadObject->getCompany());
        $event->addToken('{doi_email}', $leadObject->getEmail());
        $event->addToken('{doi_preferred_locale}', $leadObject->getPreferredLocale());
        //$event->addToken('{doi_preferred_locale}',$leadObject->getFieldValue("preferred_locale"));
        $event->addToken('{doi_opt_in_date}', $leadObject->getFieldValue("icc_doi_accepted_date"));


        $event->addToken('{doi_email_type_lead_name}', $lead['email']);
        foreach ($replaceTokens as $tokenName) {
            $event->addToken('{' . $tokenName . '}', $linkUrl . '?type=' . $tokenName . '&meta=' . $jsonMeta);
        }
    }
}
