<?php

namespace Tknoweb\DatatableBundle\Twig;

use Symfony\Component\Form\FormView;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class FilterExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('filterCount', [$this, 'getFilterCount']),
        ];
    }

    /**
     * Return the number of active filters on a given filter form
     */
    public function getFilterCount(FormView $formView): int
    {
        $count = 0;
        foreach( $formView->children as $child ){
            if(isset($child->vars['is_filtered']) && $child->vars['is_filtered']){
                $count++;
            }
        }

        return $count;
    }
}