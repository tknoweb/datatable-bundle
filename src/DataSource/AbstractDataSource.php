<?php

namespace Tknoweb\DatatableBundle\DataSource;

use Symfony\Component\Form\Form;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Tknoweb\DatatableBundle\Datatable\Datatable;
use Tknoweb\DatatableBundle\Filter\AbstractFilter;

/**
 * Datatable data source, used to populate the data
 */
abstract class AbstractDataSource
{
    protected ?array $results = null;
    protected ?int $totalCount = null;
    protected ?int $filteredCount = null;

    protected array $columns;
    protected array $sortValues;
    protected array $limitValues;
    protected ?Form $filterForm;

    abstract public function configureOptions(OptionsResolver $resolver): void;

    abstract protected function initializeDataSources(): void;

    abstract public function getResults(bool $forceRefresh = false): array;

    abstract public function getTotalCount(bool $forceRefresh = false): int;

    abstract public function getFilteredCount(bool $forceRefresh = false): int;

    abstract public function setLimitOffset(int $offset): void;

    /**
     * Apply the maximum number of results to the data source, null meaning that there is no limit
     */
    abstract protected function applyLimitMax(?int $max): void;

    public function __construct(protected array $options)
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $this->options = $resolver->resolve($options);
    }

    public function initialize(array $columns, array $sortValues, array $limitValues, ?Form $filterForm): void
    {
        $this->columns = $columns;
        $this->sortValues = $sortValues;
        $this->limitValues = $limitValues;
        $this->limitValues['max'] = self::normalizeLimitMax($limitValues['max']);
        $this->filterForm = $filterForm;

        $this->initializeDataSources();
    }

    public function setLimitMax(int $max): void
    {
        $this->limitValues['max'] = self::normalizeLimitMax($max);
        $this->applyLimitMax($this->limitValues['max']);
    }

    protected static function normalizeLimitMax(int $max): ?int
    {
        return Datatable::LIMIT_MAX_UNLIMITED === $max ? null : $max;
    }

    public function removeColumn(string $name): void
    {
        unset($this->columns[$name]);
    }

    /**
     * Add filters from the option 'filter_query' to the base source.
     * The 'filter_query' callable must mutate the given data source, its return value is discarded.
     */
    protected function addFilters(mixed $dataSource, ?Form $filterForm): void
    {
        if ($filterForm) {
            foreach ($filterForm->all() as $formElement) {
                if ($formElement->getConfig()->getType()->getInnerType() instanceof AbstractFilter) {
                    $filterQuery = $formElement->getConfig()->getOption('filter_query');

                    if (null !== $formElement->getData() && '' !== $formElement->getData()) {
                        if (is_callable($filterQuery)) {
                            call_user_func($filterQuery, $dataSource, $formElement->getData());
                        } else {
                            throw new \Exception("The 'filter_query' parameter must be a callable");
                        }
                    }
                }
            }
        }
    }
}