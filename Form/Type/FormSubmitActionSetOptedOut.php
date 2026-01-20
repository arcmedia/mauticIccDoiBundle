<?php

namespace MauticPlugin\IccDoiBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FormSubmitActionSetOptedOut extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        // This submit action has no additional settings
        $resolver->setDefaults([]);
    }

    public function getBlockPrefix(): string
    {
        return 'icc_doi_form_submit_set_opted_out';
    }
}
