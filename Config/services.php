<?php

declare(strict_types=1);

use Doctrine\ORM\EntityRepository;
use MauticPlugin\IccDoiBundle\Controller\EmailTypeController;
use MauticPlugin\IccDoiBundle\Entity\LeadDoi;
use MauticPlugin\IccDoiBundle\Entity\LeadDoiRepository;
use MauticPlugin\IccDoiBundle\EventListener\BuilderSubscriber;
use MauticPlugin\IccDoiBundle\EventListener\DoiSubscriber;
use MauticPlugin\IccDoiBundle\EventListener\FormSubscriber;
use MauticPlugin\IccDoiBundle\Helper\DoiStatusHelper;
use MauticPlugin\IccDoiBundle\Integration\IccDoiIntegration;
use MauticPlugin\IccDoiBundle\Model\LeadDoiModel;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(DoiStatusHelper::class)->args([
        service('mautic.helper.integration'),
        service('mautic.lead.repository.lead_list'),
        service('event_dispatcher'),
        service('mautic.email.model.email'),
        service('mautic.lead.model.list'),
        service('mautic.lead.model.lead'),
        service('insignio.doi.model.leaddoi'),
        service(LeadDoiModel::class),
    ])
    ->public();

    $services->alias('iccdoi.doistatus.helper', DoiStatusHelper::class)->public();

    $services->set(LeadDoiModel::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service('mautic.security'),
            service('event_dispatcher'),
            service('router'),
            service('translator'),
            service('mautic.helper.user'),
            service('logger'),
            service('mautic.helper.core_parameters'),
            service('mautic.lead.model.lead'),
        ])
        ->public();
    $services->alias('insignio.doi.model.leaddoi', LeadDoiModel::class)->public();

    $services->set(LeadDoiRepository::class)
        ->tag('doctrine.repository_service')
        ->public()
        ->args([
            service('doctrine'),
        ]);

    $services->set('mautic.integration.iccdoi', IccDoiIntegration::class)
        ->tag('mautic.integration')
        ->public()
        ->args([
            service('event_dispatcher'),
            service('mautic.helper.cache_storage'),
            service('doctrine.orm.entity_manager'),
            service('request_stack'),
            service('router'),
            service('translator'),
            service('logger'),
            service('mautic.helper.encryption'),
            service('mautic.lead.model.lead'),
            service('mautic.lead.model.company'),
            service('mautic.helper.paths'),
            service('mautic.core.model.notification'),
            service('mautic.lead.model.field'),
            service('mautic.plugin.model.integration_entity'),
            service('mautic.lead.model.dnc'),
        ]);

    $services->set(EmailTypeController::class)->public();

    $services->set(DoiSubscriber::class)->tag('kernel.event_subscriber');
    $services->set(FormSubscriber::class)->tag('kernel.event_subscriber');
    $services->set(BuilderSubscriber::class)->tag('kernel.event_subscriber');
};