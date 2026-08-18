<?php

namespace Tknoweb\DatatableBundle\Column;

use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Datatable custom column, used to render a template or custom html
 */
class CustomColumn extends AbstractColumn
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver
            ->setDefaults([
                'order_field' => false,
                'raw' => true,
            ])
            ->setAllowedValues('order_field', function ($value) {
                return false === $value || is_string($value);
            });
    }

    public function getContent(mixed $result): string
    {
        if ($this->getFormatter()) {
            return call_user_func($this->getFormatter(), $result);
        }

        if( $this->getTemplate() ) {
            return $this->twig->render($this->getTemplate(), ['result' => $result, 'column' => $this]);
        }

        throw new \Exception('The template or formatter options are mandatory for CustomColumn');
    }

    public function getOrderField(): false|string
    {
        return $this->options['order_field'];
    }

    public function isSortable(): bool
    {
        return $this->getOrderField() !== false;
    }
}