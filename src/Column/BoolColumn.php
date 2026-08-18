<?php

namespace Tknoweb\DatatableBundle\Column;

use Symfony\Component\OptionsResolver\OptionsResolver;

class BoolColumn extends AbstractValueColumn
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $columnOptions = (array)$this->parameterBag->get('datatable.column');
        $resolver
            ->setDefaults([
                'bool_text_true' => $columnOptions['bool_text_true'],
                'bool_text_false' => $columnOptions['bool_text_false'],
            ])
            ->setRequired(['bool_text_true', 'bool_text_false'])
            ->setAllowedTypes('bool_text_true', ['string'])
            ->setAllowedTypes('bool_text_false', ['string']);
    }

    public function getBoolTextTrue():string
    {
        return $this->options['bool_text_true'];
    }

    public function getBoolTextFalse():string
    {
        return $this->options['bool_text_false'];
    }

    public function transformValue(mixed $value): string
    {
        if( is_bool($value) ) {
            if(true === $value ) {
                return $this->getBoolTextTrue();
            } else {
                return $this->getBoolTextFalse();
            }
        } else {
            if( is_null($value) ) {
                return '';
            } else {
                throw new \Exception("Value parameter is not a bool");
            }
        }
    }
}