<?php

namespace Tknoweb\DatatableBundle\Column;

use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Datatable column containing an array of Action
 */
class ActionColumn extends AbstractColumn
{
    const TEMPLATE_ACTIONS = [
        'inline' => '@TknowebDatatable/_datatable_inline_actions.html.twig',
        'grouped' => '@TknowebDatatable/_datatable_grouped_actions.html.twig'
    ];

    protected array $actions = [];

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $actionColumnOptions = (array)$this->parameterBag->get('datatable.action_column');
        $resolver
            ->setDefaults([
                'template' => null,
                'template_style' => $actionColumnOptions['template_style'] ?? 'inline',
                'raw' => true
            ])
            ->setAllowedValues('template_style', function ($value) {
                return array_key_exists($value, self::TEMPLATE_ACTIONS);
            });
    }


    public function getContent(mixed $result): string
    {
        if ($this->getFormatter()) {
            return call_user_func($this->getFormatter(), $result);
        }

        if ($this->getTemplate()) {
            return $this->twig->render($this->getTemplate(), ['result' => $result, 'actions' => $this->actions, 'column' => $this]);
        }

        throw new \Exception('The template, template_style or formatter options are mandatory for ActionColumn');
    }

    public function addAction(string $name, array $options): void
    {
        $this->actions[$name] = new Action($name, $options, $this, $this->parameterBag, $this->translator, $this->twig);
    }

    public function getTemplate(): ?string
    {
        return $this->options['template'] ?: self::TEMPLATE_ACTIONS[$this->getTemplateStyle()];
    }

    public function getTemplateStyle(): string
    {
        return $this->options['template_style'];
    }

    public function getActions(): array
    {
        return $this->actions;
    }

    public function isSortable(): bool
    {
        return false;
    }
}