<?php

namespace Tknoweb\DatatableBundle\Filter;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BoolFilter extends AbstractFilter
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver
            ->setDefaults([
                'choices' => [
                    'all' => 'all',
                    'yes' => 'true',
                    'no' => 'false',
                ],
                'default_value' => 'false',
                'placeholder' => false,
            ]);
    }

    public function getParent(): ?string
    {
        return ChoiceType::class;
    }
}