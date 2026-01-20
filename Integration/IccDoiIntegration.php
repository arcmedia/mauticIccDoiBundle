<?php

namespace MauticPlugin\IccDoiBundle\Integration;

use Mautic\EmailBundle\Form\Type\EmailListType;
use Mautic\LeadBundle\Form\Type\LeadListType;
use Mautic\PluginBundle\Integration\AbstractIntegration;
use Mautic\UserBundle\Form\Type\UserListType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;

class IccDoiIntegration extends AbstractIntegration {

    public function getName(): string
    {
        return 'IccDoi';
    }

    public function getDisplayName(): string
    {
        return 'ICC Double Opt-in';
    }

    public function getAuthenticationType(): string
    {
        return 'none';
    }

    public function getSupportedFeatures(): array
    {
        return [];
    }

    public function getIcon(): string
    {
        return 'plugins/IccDoiBundle/Assets/img/icon.png';
    }

    /**
     * @return array
     */
    public function getFormSettings(): array
    {
        return [
            //'requires_callback'      => true,
            //'requires_authorization' => true,
            'requires_callback'      => false,
            'requires_authorization' => false,
        ];
    }

    public function appendToForm(&$builder, $data, $formArea): void
    {
        if ($formArea == 'features') {
            $builder->add(
                'doi_email',
                EmailListType::class,
                [
                    'label'      => 'DOI E-Mail',
                    'label_attr' => ['class' => 'control-label'],
                    'required'   => true,
                    'attr'       => [
                        'class'   => 'form-control',
                    ],
                    'multiple' => false,
                ]
            );
            $builder->add(
                'doi_email_type_mail',
                EmailListType::class,
                [
                    'label'      => 'Email Type',
                    'label_attr' => ['class' => 'control-label'],
                    'required'   => true,
                    'attr'       => [
                        'class'   => 'form-control',
                    ],
                    'multiple' => false,
                ]
            );
            $builder->add(
                'doi_email_type_receiver',
                UserListType::class,
                [
                    'label'      => 'Email Type Receiver',
                    'label_attr' => ['class' => 'control-label'],
                    'required'   => true,
                    'attr'       => [
                        'class'   => 'form-control',
                    ],
                    'multiple' => false,
                ]
            );
        }
    }
}