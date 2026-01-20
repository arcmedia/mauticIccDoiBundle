<?php

namespace MauticPlugin\IccDoiBundle;

final class IccDoiEvents
{
    /**
     * The icc.doi.on_status_change event is fired when the doi status of a lead changes.
     *
     * The event listener receives a
     * MauticPlugin\MauticIccDoiBundle\Event\DoiChangeEvent
     */
    const ON_DOI_STATUS_CHANGE = 'icc.doi.on_status_change';

    /**
     * The icc.doi.on_campaign_trigger_decision event is fired when the campaign check doi status decision triggers.
     *
     * The event listener receives a
     * Mautic\CampaignBundle\Event\CampaignExecutionEvent
     *
     * @var string
     */
    const ON_CHECK_DOI_TRIGGER_DECISION = 'icc.doi.on_campaign_trigger_decision';
}