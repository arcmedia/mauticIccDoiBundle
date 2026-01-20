<?php

namespace MauticPlugin\IccDoiBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FormSubmitActionSendDoi extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        // This action has no configurable fields
        $resolver->setDefaults([]);
    }

    public function getBlockPrefix(): string
    {
        return 'icc_doi_form_submit_send_doi';
    }
}
