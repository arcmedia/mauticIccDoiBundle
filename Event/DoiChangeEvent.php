<?php

namespace MauticPlugin\IccDoiBundle\Event;

use Mautic\CoreBundle\Event\CommonEvent;
use Mautic\LeadBundle\Entity\Lead;

/**
 * Class DoiChangeEvent.
 */
class DoiChangeEvent extends CommonEvent
{
    /**
     * @var Lead
     */
    protected $lead;

    /**
     * @var string
     */
    protected $doiStatus;

    /**
     * PageHitEvent constructor.
     *
     * @param Lead $lead
     * @param string $doiStatus
     */
    public function __construct(Lead $lead, string $doiStatus)
    {
        parent::__construct($lead);
        $this->lead           = $lead;
        $this->doiStatus      = $doiStatus;
    }

    /**
     * Get Lead.
     *
     * @return Lead
     */
    public function getLead(): Lead
    {
        return $this->lead;
    }

    /**
     * Get Doi Status.
     *
     * @return string
     */
    public function getDoiStatus(): string
    {
        return $this->doiStatus;
    }

}
