<?php

namespace Tknoweb\DatatableBundle\Filter;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class ChoiceFilter extends AbstractFilter
{
    public function getParent(): ?string
    {
        return ChoiceType::class;
    }
}