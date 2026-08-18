<?php

namespace Tknoweb\DatatableBundle\Column;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Datatable Column that will hold a value (text, bool ...)
 */
abstract class AbstractValueColumn extends AbstractColumn
{
    protected PropertyAccessor $propertyAccessor;

    /**
     * Transform a mixed value into a string, depending on the column type
     */
    abstract public function transformValue(mixed $value): string;

    public function __construct(
        protected string                $name,
        protected array                 $options,
        protected ParameterBagInterface $parameterBag,
        protected TranslatorInterface   $translator,
        protected Environment           $twig
    )
    {
        parent::__construct($name, $options, $parameterBag, $translator, $twig);

        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * Get the column content to be shown
     */
    public function getContent(mixed $result): string
    {
        $rawValue = $this->propertyAccessor->getValue($result, $this->getField());
        $value = $this->transformValue($rawValue);

        if ($this->getFormatter()) {
            $value = call_user_func($this->getFormatter(), $value, $result);
        }

        if ($this->getTemplate()) {
            return $this->twig->render($this->getTemplate(), ['result' => $result, 'cell' => $value, 'column' => $this]);
        }

        return $value;
    }

    /**
     * Get the column raw content before transformation
     */
    public function getRawContent(mixed $result): mixed
    {
        return $this->propertyAccessor->getValue($result, $this->getField());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $columnOptions = (array)$this->parameterBag->get('datatable.column');
        $resolver
            ->setDefaults([
                'field' => $this->name,
                'order_field' => null,
                'route' => $columnOptions['route'] ?? null,
                'route_parameters' => $columnOptions['route_parameters'] ?? null
            ])
            ->setAllowedTypes('field', ['string'])
            ->setAllowedValues('order_field', function ($value) {
                return null == $value || false === $value || is_string($value);
            })
            ->setAllowedTypes('route', ['null', 'string'])
            ->setAllowedTypes('route_parameters', ['null', 'array', 'callable']);
    }

    public function getField(): string
    {
        return $this->options['field'];
    }

    public function getOrderField(): null|false|string
    {
        return null !== $this->options['order_field'] ? $this->options['order_field'] : $this->options['field'];
    }

    public function getRoute(): ?string
    {
        return $this->options['route'];
    }

    public function getRouteParameters(): null|array|callable
    {
        return $this->options['route_parameters'];
    }

    public function isSortable(): bool
    {
        return $this->getOrderField() !== false;
    }
}