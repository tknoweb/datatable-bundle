<?php

namespace Tknoweb\DatatableBundle\Column;

use Symfony\Component\OptionsResolver\OptionsResolver;

class DateTimeColumn extends AbstractValueColumn
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $columnOptions = (array)$this->parameterBag->get('datatable.column');
        $resolver
            ->setDefaults([
                'display_time' => false,
                'date_time_format' => $columnOptions['date_time_format'],
                'date_format' => $columnOptions['date_format'],
            ])
            ->setRequired(['display_time', 'date_time_format', 'date_format'])
            ->setAllowedTypes('display_time', ['bool'])
            ->setAllowedTypes('date_time_format', ['string'])
            ->setAllowedTypes('date_format', ['string']);
    }

    public function getDateTimeFormat():string
    {
        return $this->options['date_time_format'];
    }

    public function getDateFormat():string
    {
        return $this->options['date_format'];
    }

    public function isDisplayTime():bool
    {
        return $this->options['display_time'];
    }

    public function transformValue(mixed $value): string
    {
        if ($value === null) {
            return "";
        }

        if (!$value instanceof \DateTime && !$value instanceof \DateTimeImmutable) {
            throw new \Exception("Value parameter is not a DateTime object");
        }

        return $value->format($this->isDisplayTime() ? $this->getDateTimeFormat() : $this->getDateFormat());
    }
}