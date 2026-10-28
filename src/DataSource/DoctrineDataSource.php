<?php

namespace Tknoweb\DatatableBundle\DataSource;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\Form\Form;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Tknoweb\DatatableBundle\Datatable\Datatable;
use Tknoweb\DatatableBundle\Datatable\DatatableResult;

/**
 * Doctrine specific data source
 */
class DoctrineDataSource extends AbstractDataSource
{
    const DEFAULT_HYDRATION_MODE = AbstractQuery::HYDRATE_OBJECT;

    protected ?QueryBuilder $resultsDataSource = null;
    protected ?QueryBuilder $filteredCountDataSource = null;
    protected ?QueryBuilder $totalCountDataSource = null;

    public function getQueryBuilder(): QueryBuilder|callable
    {
        return $this->options['query_builder'];
    }

    public function getFilteredCountDataSource(): ?QueryBuilder
    {
        return $this->filteredCountDataSource;
    }

    public function getHydrationMode(): int
    {
        return $this->options['hydration_mode'];
    }

    protected function initializeDataSources(): void
    {
        $baseDataSource = $this->getQueryBuilder();
        if (is_callable($baseDataSource)) {
            $baseDataSource = call_user_func($baseDataSource);
        }

        $rootAlias = $baseDataSource->getRootAliases()[0];

        //Total count source
        $this->totalCountDataSource = (clone($baseDataSource))->select("COUNT(DISTINCT $rootAlias.id)")->resetDQLPart('orderBy');

        //Add the filters
        $tmpDataSource = clone $baseDataSource;
        $this->addFilters($tmpDataSource, $this->filterForm);

        //Filtered count source
        $this->filteredCountDataSource = (clone($tmpDataSource))->select("COUNT(DISTINCT $rootAlias.id)")->resetDQLPart('orderBy');

        //Add the orderBy clause
        foreach ($this->sortValues as $sortValue) {
            if (preg_match('/^[a-z0-9_]+$/i', $sortValue['field'])) {
                $sortValue['field'] = $rootAlias . "." . $sortValue['field'];
            }

            $tmpDataSource->addOrderBy($sortValue['field'], $sortValue['direction']);
        }

        //Break ties on the identifier, in the direction of the last sort: rows sharing the same sorted
        //value have no guaranteed order in SQL, and may otherwise be shown on two pages or on none.
        //Skipped when already sorted on the identifier: SQL Server rejects a column listed twice
        if (!$this->isOrderedBy($tmpDataSource, "$rootAlias.id")) {
            $lastSortValue = end($this->sortValues);
            $tmpDataSource->addOrderBy("$rootAlias.id", $lastSortValue ? $lastSortValue['direction'] : Datatable::SORT_ORDER_ASCENDING);
        }

        //Add the limit clause
        $tmpDataSource->setFirstResult($this->limitValues['offset'])->setMaxResults($this->limitValues['max']);
        //Results data source
        $this->resultsDataSource = $tmpDataSource;
    }

    protected function isOrderedBy(QueryBuilder $queryBuilder, string $field): bool
    {
        foreach ($queryBuilder->getDQLPart('orderBy') as $orderBy) {
            foreach ($orderBy->getParts() as $part) {
                if (preg_replace('/\s+(asc|desc)$/i', '', trim($part)) === $field) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getResults(bool $forceRefresh = false): array
    {
        if (null === $this->results || $forceRefresh) {
            $this->results = [];
            $results = new Paginator($this->resultsDataSource->getQuery()->setHydrationMode($this->getHydrationMode()), true);
            foreach ($results as $result) {
                $this->results[] = new DatatableResult($result, $this->columns);
            }
        }

        return $this->results;
    }

    public function getTotalCount(bool $forceRefresh = false): int
    {
        if (null === $this->totalCount || $forceRefresh) {
            $this->totalCount = $this->totalCountDataSource->getQuery()->getSingleScalarResult();
        }

        return $this->totalCount;
    }

    public function getFilteredCount(bool $forceRefresh = false): int
    {
        if (null === $this->filteredCount || $forceRefresh) {
            $this->filteredCount = $this->filteredCountDataSource->getQuery()->getSingleScalarResult();
        }

        return $this->filteredCount;
    }

    public function setLimitOffset(int $offset): void
    {
        $this->limitValues['offset'] = $offset;
        $this->resultsDataSource?->setFirstResult($this->limitValues['offset']);
    }

    protected function applyLimitMax(?int $max): void
    {
        $this->resultsDataSource?->setMaxResults($max);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefault('hydration_mode', self::DEFAULT_HYDRATION_MODE)
            ->setRequired(['query_builder', 'hydration_mode'])
            ->setAllowedTypes('query_builder', [QueryBuilder::class, 'callable'])
            ->setAllowedTypes('hydration_mode', ['int']);
    }
}