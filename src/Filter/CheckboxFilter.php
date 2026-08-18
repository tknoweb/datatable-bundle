<?php

namespace Tknoweb\DatatableBundle\Filter;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class CheckboxFilter extends AbstractFilter
{
    public function getParent(): ?string
    {
        return CheckboxType::class;
    }
}