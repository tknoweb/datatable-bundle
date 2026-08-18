<?php

namespace Tknoweb\DatatableBundle\Datatable;

/**
 * Datatable result (line) containing cells
 */
class DatatableResult
{
    protected array $cells = [];

    public function __construct(protected readonly mixed $result, protected readonly array $columns)
    {
        foreach( $columns as $column ) {
            $this->cells[] = new DatatableResultCell($column, $result);
        }
    }

    public function getValue(): mixed
    {
        return $this->result;
    }

    public function getCells(): array
    {
        return $this->cells;
    }

    public function getCell(string $columnName): DatatableResultCell
    {
        foreach( $this->cells as $cell ) {
            if( $cell->getColumn()->getName() == $columnName ) {
                return $cell;
            }
        }

        throw new \Exception(sprintf("The passed columnName '%s' does not exists.", $columnName));
    }

    public function getActionCells(): array
    {
        $actionCells = [];
        foreach( $this->cells as $cell ){
            if( $cell->isActionCell() ) {
                $actionCells[] = $cell;
            }
        }
        return $actionCells;
    }
}