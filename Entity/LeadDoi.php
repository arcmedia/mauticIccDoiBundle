<?php

declare(strict_types=1);

namespace MauticPlugin\IccDoiBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\LeadBundle\Entity\Lead;

class LeadDoi
{
    /**
     * @var Lead
     */
    private $lead;

    /**
     * @var string
     */
    private $doi_status;

    /**
     * @var string
     */
    private $activation_link;

    /**
     * @var \DateTime
     */
    private $expiration_date;

    /**
     * @var \DateTime
     */
    private $doi_accepted_date;

    /**
     * @var \DateTime
     */
    private $doi_declined_date;

    /**
     * @var string
     */
    private $token;

    /**
     * @var string
     */
    private $doi_type;


    public function __construct($lead) {
        $this->setLead($lead);
    }

    public static function loadMetadata(ORM\ClassMetadata $metadata)
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable('icc_leads_doi')
            ->setCustomRepositoryClass('MauticPlugin\IccDoiBundle\Entity\LeadDoiRepository');

        $builder->createManyToOne('lead', Lead::class)
            ->isPrimaryKey()
            ->addJoinColumn('lead_id', 'id', false, false, 'CASCADE')
            ->build();

        $builder->createField('doi_status', 'string')
            ->columnName('doi_status')
            ->option('default', '')
            ->build();

        $builder->createField('activation_link', 'string')
            ->columnName('activation_link')
            ->option('default', '')
            ->nullable(true)
            ->build();

        $builder->createField('expiration_date', 'datetime')
            ->columnName('expiration_date')
            ->nullable(true)
            ->build();

        $builder->createField('doi_accepted_date', 'datetime')
            ->columnName('doi_accepted_date')
            ->nullable(true)
            ->build();

        $builder->createField('doi_declined_date', 'datetime')
            ->columnName('doi_declined_date')
            ->nullable(true)
            ->build();

        $builder->createField('token', 'string')
            ->columnName('token')
            ->option('default', '')
            ->nullable(true)
            ->build();

        $builder->createField('doi_type', 'string')
            ->columnName('doi_type')
            ->option('default', '')
            ->nullable(true)
            ->build();
    }

    public function getId()
    {
        return $this->lead ? $this->lead->getId() : null;
    }

    /**
     * @return mixed
     */
    public function getLead()
    {
        return $this->lead;
    }

    /**
     * @param $lead
     *
     * @return void
     */
    public function setLead($lead)
    {
        $this->lead = $lead;
    }

    /**
     * @return string
     */
    public function getDoiStatus(): string
    {
        return $this->doi_status;
    }

    /**
     * @param string $doi_status
     */
    public function setDoiStatus(string $doi_status): void
    {
        $this->doi_status = $doi_status;

    }

    /**
     * @return string
     */
    public function getActivationLink(): string
    {
        return $this->activation_link;
    }

    /**
     * @param string $activation_link
     */
    public function setActivationLink(string $activation_link): void
    {
        $this->activation_link = $activation_link;
    }

    /**
     * @return \DateTime
     */
    public function getExpirationDate(): ?\DateTime
    {
        return $this->expiration_date;
    }

    /**
     * @param \DateTime $expiration_date
     */
    public function setExpirationDate(\DateTime $expiration_date): void
    {
        $this->expiration_date = $expiration_date;
    }

    /**
     * @return \DateTime
     */
    public function getDoiAcceptedDate(): ?\DateTime
    {
        return $this->doi_accepted_date;
    }

    /**
     * @param \DateTime $doi_accepted_date
     */
    public function setDoiAcceptedDate(\DateTime $doi_accepted_date): void
    {
        $this->doi_accepted_date = $doi_accepted_date;
    }

    /**
     * @return \DateTime
     */
    public function getDoiDeclinedDate(): ?\DateTime
    {
        return $this->doi_declined_date;
    }

    /**
     * @param \DateTime $doi_declined_date
     */
    public function setDoiDeclinedDate(\DateTime $doi_declined_date): void
    {
        $this->doi_declined_date = $doi_declined_date;
    }

    /**
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @param string $token
     */
    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    /**
     * @return string
     */
    public function getDoiType(): string
    {
        return $this->doi_type;
    }

    /**
     * @param string doi_type
     */
    public function setDoiType(string $doi_type): void
    {
        $this->doi_type = $doi_type;
    }
}
