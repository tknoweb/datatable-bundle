<?php

namespace Tknoweb\DatatableBundle\Datatable;

use Tknoweb\DatatableBundle\Column\AbstractColumn;
use Tknoweb\DatatableBundle\Column\AbstractValueColumn;
use Tknoweb\DatatableBundle\Column\ActionColumn;
use Tknoweb\DatatableBundle\Column\BoolColumn;

/**
 * Datatable result cell
 */
class DatatableResultCell
{
    protected string $value;
    protected mixed $rawValue = null;


    public function __construct(protected AbstractColumn $column, mixed $result)
    {
        $this->value = $column->getContent($result);
        if( $this->isValueCell() ){
            $this->rawValue = $this->column->getRawContent($result);
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function getContent(): string
    {
        return $this->value;
    }

    public function getRawContent(): mixed
    {
        return $this->rawValue;
    }

    public function getCssClass(): ?string
    {
        return $this->column->getCellCssClass();
    }

    public function getRoute(): ?string
    {
        return $this->column instanceof AbstractValueColumn ? $this->column->getRoute() : null;
    }

    public function getRouteParameters(mixed $result): ?array
    {
        if (!$this->column instanceof AbstractValueColumn) {
            return null;
        }

        $routeParameters = $this->column->getRouteParameters();

        return is_callable($routeParameters) ? call_user_func($routeParameters, $result) : $routeParameters;
    }

    public function getColumn(): AbstractColumn
    {
        return $this->column;
    }

    public function isRaw(): bool
    {
        return $this->column->getRaw();
    }

    public function isActionCell(): bool
    {
        return $this->column instanceof ActionColumn;
    }

    public function isValueCell(): bool
    {
        return $this->column instanceof AbstractValueColumn;
    }

    public function isBoolCell(): bool
    {
        return $this->column instanceof BoolColumn;
    }

    public function show(): bool
    {
        return $this->column->getShow();
    }
}