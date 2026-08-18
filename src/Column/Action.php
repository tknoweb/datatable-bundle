<?php

namespace Tknoweb\DatatableBundle\Column;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Contracts\Translation\TranslatorInterface;

use Twig\Environment;

/**
 * Datatable action (edit, delete ...)
 */
class Action
{
    const DEFAULT_TEMPLATE_PATH = '@TknowebDatatable/action/';
    const DEFAULT_TEMPLATE = '_action.html.twig';

    public function __construct(
        protected string                $name,
        protected array                 $options,
        protected ActionColumn          $actionColumn,
        protected ParameterBagInterface $parameterBag,
        protected TranslatorInterface   $translator,
        protected Environment           $twig
    )
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $this->options = $resolver->resolve($options);
    }

    public function getContent(mixed $result): string
    {
        if ($this->getFormatter()) {
            return call_user_func($this->getFormatter(), $result, $this);
        }

        if ($this->getTemplate()) {
            return $this->renderTemplate($result);
        }

        throw new \Exception('The template or formatter options are mandatory for Action');
    }

    public function renderTemplate(mixed $result): string
    {
        return $this->twig->render($this->getTemplate(), ['result' => $result, 'action' => $this]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $actionOptions = (array)$this->parameterBag->get('datatable.action');
        $converter = new CamelCaseToSnakeCaseNameConverter();

        $template = $this->twig->resolveTemplate([
            self::DEFAULT_TEMPLATE_PATH . '_' . $converter->normalize($this->name) . '.html.twig',
            self::DEFAULT_TEMPLATE_PATH . self::DEFAULT_TEMPLATE
        ])->getTemplateName();

        $resolver
            ->setDefaults([
                'label' => ucfirst($this->translator->trans($this->name)),
                'tooltip' => false,
                'route' => null,
                'route_parameters' => null,
                'icon_css_class' => false,
                'css_class' => $actionOptions['css_class'] ?? null,
                'confirm_message' => false,
                'credential' => null,
                'template' => $template,
                'formatter' => null,
                'stimulus_action' => null,
                'target' => null
            ])
            ->setAllowedValues('label', function ($value) {
                return false === $value || is_string($value) || is_callable($value);
            })
            ->setAllowedValues('tooltip', function ($value) {
                return null == $value || false === $value || is_string($value) || is_callable($value);
            })
            ->setAllowedTypes('route', ['null', 'string'])
            ->setAllowedTypes('route_parameters', ['null', 'array', 'callable'])
            ->setAllowedValues('icon_css_class', function ($value) {
                return null == $value || false === $value || is_string($value);
            })
            ->setAllowedTypes('css_class', ['null', 'string'])
            ->setAllowedValues('confirm_message', function ($value) {
                return null == $value || false === $value || is_string($value) || is_callable($value);
            })
            ->setAllowedTypes('credential', ['null', 'string'])
            ->setAllowedTypes('template', ['null', 'string'])
            ->setAllowedTypes('formatter', ['null', 'callable'])
            ->setAllowedTypes('stimulus_action', ['null', 'array'])
            ->setAllowedTypes('target', ['null', 'string']);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): null|string|callable
    {
        return $this->options['label'];
    }

    public function getComputedLabel(mixed $result): null|string
    {
        if (is_callable($this->getLabel())) {
            return call_user_func($this->getLabel(), $result);
        } else {
            return $this->getLabel();
        }
    }

    public function getRoute(): ?string
    {
        return $this->options['route'];
    }

    public function getRouteParameters(): null|array|callable
    {
        return $this->options['route_parameters'];
    }

    public function getComputedRouteParameters(mixed $result): null|array
    {
        if (is_callable($this->getRouteParameters())) {
            return call_user_func($this->getRouteParameters(), $result);
        } else {
            return $this->getRouteParameters();
        }
    }

    public function getIconCssClass(): null|false|string
    {
        return null !== $this->options['icon_css_class'] ? $this->options['icon_css_class'] : $this->name;
    }

    public function getCssClass(): ?string
    {
        return $this->options['css_class'];
    }

    public function getConfirmMessage(): null|false|string|callable
    {
        return $this->options['confirm_message'];
    }

    public function getComputedConfirmMessage(mixed $result): null|string
    {
        if (is_callable($this->getConfirmMessage())) {
            return call_user_func($this->getConfirmMessage(), $result);
        } else {
            return $this->getConfirmMessage();
        }
    }

    public function getCredential(): null|string
    {
        return $this->options['credential'];
    }

    public function getTemplate(): ?string
    {
        return $this->options['template'];
    }

    public function getFormatter(): callable|null
    {
        return $this->options['formatter'];
    }

    public function getTooltip(): null|false|string|callable
    {
        return $this->options['tooltip'];
    }

    public function getComputedTooltip(mixed $result): null|string
    {
        if (is_callable($this->getTooltip())) {
            return call_user_func($this->getTooltip(), $result);
        } elseif( null === $this->getTooltip() ) {
            return $this->getComputedLabel($result);
        } elseif( false === $this->getTooltip() ) {
            return '';
        } else {
            return $this->getTooltip();
        }
    }

    public function getStimulusAction(): ?array
    {
        return $this->options['stimulus_action'];
    }

    public function getTarget(): ?string
    {
        return $this->options['target'];
    }

    public function getColumnTemplateStyle(): string
    {
        return $this->actionColumn->getTemplateStyle();
    }
}