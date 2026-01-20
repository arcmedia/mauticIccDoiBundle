<?php

namespace MauticPlugin\IccDoiBundle\Model;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Model\AbstractCommonModel;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\IccDoiBundle\Entity\LeadDoi;
use MauticPlugin\IccDoiBundle\Entity\LeadDoiRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Class LeadDoiModel.
 */
class LeadDoiModel extends AbstractCommonModel
{
    /**
     * @var LeadModel
     */
    protected $leadModel;

    /**
     * CitrixModel constructor.
     *
     * @param LeadModel $leadModel
     */
    public function __construct(
        EntityManagerInterface $em,
        CorePermissions $security,
        EventDispatcherInterface $dispatcher,
        UrlGeneratorInterface $router,
        Translator $translator,
        UserHelper $userHelper,
        LoggerInterface $logger,
        CoreParametersHelper $coreParametersHelper,
        LeadModel $leadModel
    ) {
        parent::__construct($em, $security, $dispatcher, $router, $translator, $userHelper, $logger, $coreParametersHelper);
        $this->leadModel = $leadModel;
    }

    /**
     * {@inheritdoc}
     *
     * @return LeadDoiRepository
     */
    public function getRepository(): LeadDoiRepository
    {
        return $this->em->getRepository(LeadDoi::class);
    }

    public function save(LeadDoi $leadDoi): void
    {
        $this->em->persist($leadDoi);
        $this->em->flush();
    }
}