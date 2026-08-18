<?php

namespace Tknoweb\DatatableBundle\Datatable;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Factory helper to create Datatable
 */
class DatatableFactory
{
    public function __construct(
        protected ParameterBagInterface $parameterBag,
        protected TranslatorInterface   $translator,
        protected Environment           $twig,
        protected RequestStack          $requestStack,
        protected FormFactoryInterface  $formFactory
    )
    {
    }

    /**
     * Create a datatable with a default name
     */
    public function create(array $options = []): Datatable
    {
        return $this->createNamed(Datatable::DEFAULT_NAME, $options);
    }

    /**
     * Create a datatable with a specific name
     */
    public function createNamed(string $name, array $options = []): Datatable
    {
        return new Datatable($name, $options, $this->parameterBag, $this->translator, $this->twig, $this->requestStack, $this->formFactory);
    }
}