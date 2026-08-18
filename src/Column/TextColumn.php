<?php

namespace Tknoweb\DatatableBundle\Column;

class TextColumn extends AbstractValueColumn
{
    public function transformValue(mixed $value): string
    {
        if( $value instanceof \BackedEnum) {
            //If a specific trans method is implemented
            if( method_exists($value, 'trans') ) {
                return $value->trans($this->translator);
            } else {
                return $this->translator->trans($value->value);
            }
        } else {
            return (string)$value;
        }
    }
}