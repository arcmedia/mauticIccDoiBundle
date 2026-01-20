<?php

namespace MauticPlugin\IccDoiBundle;

use Doctrine\DBAL\Schema\Schema;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Model\FieldModel;
use Mautic\PluginBundle\Entity\Plugin;
use Mautic\IntegrationsBundle\Bundle\AbstractPluginBundle;
use Symfony\Component\DependencyInjection\ContainerInterface;

class IccDoiBundle extends AbstractPluginBundle
{
    public static function onPluginInstall(
        Plugin $plugin,
        ContainerInterface $container,
        array $metadata = [],
        ?Schema $installedSchema = null
    ) {
        self::createDoiFields($container);
        parent::onPluginInstall($plugin, $container, $metadata, $installedSchema);
    }

    public static function onPluginUpdate(
        Plugin $plugin,
        ContainerInterface $container,
        array $metadata = [],
        ?Schema $installedSchema = null
    ): void {
        parent::onPluginUpdate($plugin, $container, $metadata, $installedSchema);
    }

    private static function createDoiFields(ContainerInterface $container): void
    {
        /** @var FieldModel $fieldModel */
        $fieldModel = $container->get('mautic.lead.model.field');
        $coreFields = $fieldModel->getFieldList()['Core'];

        // --- DOI STATUS ---
        if (!isset($coreFields['icc_doi_status'])) {
            $field = new LeadField();
            $field->setLabel('Double Opt-In Status');
            $field->setAlias('icc_doi_status');
            $field->setType('select');
            $field->setIsPublished(true);
            $field->setDefaultValue('doi_none');

            $fieldModel->setFieldProperties($field, [
                'list' => [
                    ['label' => 'None',      'value' => IccDoiStatus::DOI_NONE],
                    ['label' => 'Pending',   'value' => IccDoiStatus::DOI_PENDING],
                    ['label' => 'Confirmed', 'value' => IccDoiStatus::DOI_CONFIRMED],
                    ['label' => 'Opted-Out', 'value' => IccDoiStatus::DOI_OPTED_OUT],
                ]
            ]);

            $fieldModel->save($field);
        }

        // --- ACCEPTED DATE ---
        if (!isset($coreFields['icc_doi_accepted_date'])) {
            $field = new LeadField();
            $field->setLabel('Datetime DOI Accepted');
            $field->setAlias('icc_doi_accepted_date');
            $field->setType('datetime');
            $field->setIsPublished(true);

            $fieldModel->save($field);
        }

        // --- DOI TYPE ---
        if (!isset($coreFields['icc_doi_type'])) {
            $field = new LeadField();
            $field->setLabel('DOI Type');
            $field->setAlias('icc_doi_type');
            $field->setType('select');
            $field->setIsPublished(true);

            $fieldModel->setFieldProperties($field, [
                'list' => [
                    ['label' => 'Prices', 'value' => 'prices'],
                    ['label' => 'Public', 'value' => 'public'],
                    ['label' => 'Press',  'value' => 'press'],
                ]
            ]);

            $fieldModel->save($field);
        }
    }
}