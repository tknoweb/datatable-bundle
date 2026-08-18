<?php

namespace Tknoweb\DatatableBundle\Column;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Datatable Column
 */
abstract class AbstractColumn
{
    /**
     * Get the column string content
     */
    abstract public function getContent(mixed $result): string;

    /**
     * Check if the column is sortable
     */
    abstract public function isSortable(): bool;

    public function __construct(
        protected string                $name,
        protected array                 $options,
        protected ParameterBagInterface $parameterBag,
        protected TranslatorInterface   $translator,
        protected Environment           $twig
    )
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $this->options = $resolver->resolve($options);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $columnOptions = (array)$this->parameterBag->get('datatable.column');
        $resolver
            ->setDefaults([
                'label' => ucfirst($this->translator->trans($this->name)),
                'template' => null,
                'head_css_class' => $columnOptions['head_css_class'] ?? null,
                'cell_css_class' => $columnOptions['cell_css_class'] ?? null,
                'formatter' => null,
                'show' => true,
                'raw' => false
            ])
            ->setAllowedValues('label', function ($value) {
                return false === $value || is_string($value);
            })
            ->setAllowedTypes('template', ['null', 'string'])
            ->setAllowedTypes('head_css_class', ['null', 'string'])
            ->setAllowedTypes('cell_css_class', ['null', 'string'])
            ->setAllowedTypes('formatter', ['null', 'callable'])
            ->setAllowedTypes('show', ['null', 'bool'])
            ->setAllowedTypes('raw', ['bool']);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): ?string
    {
        return $this->options['label'];
    }

    public function getTemplate(): ?string
    {
        return $this->options['template'];
    }

    public function getHeadCssClass(): ?string
    {
        return $this->options['head_css_class'];
    }

    public function getCellCssClass(): ?string
    {
        return $this->options['cell_css_class'];
    }

    /**
     * Whether the column content is rendered as is, without HTML escaping
     */
    public function getRaw(): bool
    {
        return $this->options['raw'];
    }

    public function getFormatter(): callable|null
    {
        return $this->options['formatter'];
    }

    public function getShow(): bool
    {
        return $this->options['show'];
    }
}