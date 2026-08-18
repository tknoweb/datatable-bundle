<?php

namespace Tknoweb\DatatableBundle\Filter;

use Symfony\Component\Form\Extension\Core\Type\TextType;

class TextFilter extends AbstractFilter
{
    public function getParent(): ?string
    {
        return TextType::class;
    }
}