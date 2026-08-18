<?php

namespace Tknoweb\DatatableBundle\Datatable;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tknoweb\DatatableBundle\Column\AbstractColumn;
use Tknoweb\DatatableBundle\Column\ActionColumn;
use Tknoweb\DatatableBundle\DataSource\AbstractDataSource;
use Tknoweb\DatatableBundle\Filter\AbstractBaseFilter;
use Twig\Environment;

/**
 * Datatable main object containing the columns, data etc.
 */
class Datatable
{
    const DEFAULT_TEMPLATE = '@TknowebDatatable/_datatable.html.twig';
    const DEFAULT_TEMPLATE_TBODY = '@TknowebDatatable/_datatable_tbody.html.twig';
    const DEFAULT_NAME = "datatable";

    //Limit max value meaning that every result is displayed, without any limit
    const LIMIT_MAX_UNLIMITED = -1;

    //Query parameters name
    const SORT_ORDER_ASCENDING = "asc";
    const SORT_ORDER_DESCENDING = "desc";
    const SORT_PARAMETER_NAME = 'sort';
    const LIMIT_OFFSET_PARAMETER_NAME = 'offset';
    const LIMIT_MAX_PARAMETER_NAME = 'max';
    const RESET_FILTERS_PARAMETER_NAME = 'resetFilters';
    const RESET_PAGINATION_PARAMETER_NAME = 'resetPagination';
    const FORCE_FILTERS_PARAMETER_NAME = 'forceFilters';

    //Session parameters name
    const DATATABLE_SESSION_FILTERS_NAME = 'filters';
    const DATATABLE_SESSION_LIMIT_NAME = 'limit';
    const DATATABLE_SESSION_SORT_NAME = 'sort';

    protected array $columns = [];
    protected array $sortValues = [];
    protected array $limitValues = [];

    protected ?Request $request = null;
    protected ?FormInterface $filterForm = null;

    protected ?AbstractDataSource $dataSource = null;

    public function __construct(
        protected string                $name,
        protected array                 $options,
        protected ParameterBagInterface $parameterBag,
        protected TranslatorInterface   $translator,
        protected Environment           $twig,
        protected RequestStack          $requestStack,
        protected FormFactoryInterface  $formFactory
    )
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $this->options = $resolver->resolve($options);

        $this->limitValues = ['offset' => 0, 'max' => $this->options['default_limit_max']];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $datatableOptions = (array)$this->parameterBag->get('datatable.datatable');
        $resolver
            ->setDefaults([
                'possible_limit_max' => $datatableOptions['possible_limit_max'],
                'default_limit_max' => $datatableOptions['default_limit_max'],
                'css_class' => $datatableOptions['css_class'] ?? null,
                'template' => null,
                'template_tbody' => self::DEFAULT_TEMPLATE_TBODY,
                'scroll_on_change' => true
            ])
            ->setRequired(['default_limit_max', 'possible_limit_max'])
            ->setAllowedTypes('possible_limit_max', ['int[]'])
            ->setAllowedValues('possible_limit_max', function ($values) {
                foreach ($values as $value) {
                    if ($value <= 0 && self::LIMIT_MAX_UNLIMITED !== $value) {
                        return false;
                    }
                }

                return true;
            })
            ->setAllowedValues('default_limit_max', function ($value) use ($datatableOptions) {
                return is_int($value) && ($value > 0 || self::LIMIT_MAX_UNLIMITED === $value);
            })
            ->setAllowedTypes('css_class', ['null', 'string'])
            ->setAllowedTypes('template', ['null', 'string'])
            ->setAllowedTypes('template_tbody', ['null', 'string'])
            ->setAllowedTypes('scroll_on_change', ['bool']);
    }

    public function setFilterForm(string $class, mixed $data = null, array $options = []): self
    {
        if (!is_subclass_of($class, AbstractBaseFilter::class)) {
            throw new \Exception(sprintf("The passed filter class '%s' must extend AbstractBaseFilter.", $class));
        }

        $this->filterForm = $this->formFactory->create($class, $data, array_merge($options, ['datatable' => $this]));

        return $this;
    }

    public function addColumn(string $name, string $columnClass, array $options = []): self
    {
        if (!is_subclass_of($columnClass, AbstractColumn::class)) {
            throw new \Exception(sprintf("The class '%s' is not an AbstractColumn", $columnClass));
        }

        $this->columns[$name] = new $columnClass($name, $options, $this->parameterBag, $this->translator, $this->twig);

        return $this;
    }

    public function removeColumn(string $name): self
    {
        unset($this->columns[$name]);
        $this->dataSource?->removeColumn($name);

        return $this;
    }

    public function addAction(string $name, array $options = []): self
    {
        $lastAddedActionColumn = null;
        foreach ($this->columns as $column) {
            if ($column instanceof ActionColumn) {
                $lastAddedActionColumn = $column;
            }
        }

        if (null === $lastAddedActionColumn) {
            throw new \Exception(sprintf("No ActionColumn was added to the Datatable."));
        }

        $lastAddedActionColumn->addAction($name, $options);

        return $this;
    }

    public function addOrderBy(string $field, string $direction = self::SORT_ORDER_ASCENDING): self
    {
        if (!array_key_exists($field, $this->columns)) {
            throw new \Exception(sprintf("The field '%s' has not been added to the Datatable", $field));
        }

        if (!in_array($direction, [self::SORT_ORDER_ASCENDING, self::SORT_ORDER_DESCENDING])) {
            throw new \Exception(sprintf("The direction '%s' is not a valid value: '%s'", $direction, print_r([self::SORT_ORDER_ASCENDING, self::SORT_ORDER_DESCENDING], true)));
        }

        if (!$this->columns[$field]->isSortable()) {
            throw new \Exception(sprintf("The field '%s' is not sortable and cannot be used to order the Datatable", $field));
        }

        $this->sortValues[] = ['field' => $this->columns[$field]->getOrderField(), 'field_name' => $field, 'direction' => $direction, ];

        return $this;
    }

    public function getColumnCurrentSort(AbstractColumn $column): ?string
    {
        if ($column->isSortable()) {
            foreach ($this->sortValues as $sortValue) {
                if ($sortValue['field'] == $column->getOrderField()) {
                    return $sortValue['direction'];
                }
            }
        }

        return null;
    }

    public function setDataSource(string $dataSourceClass, array $options = []): self
    {
        if (!is_subclass_of($dataSourceClass, AbstractDataSource::class)) {
            throw new \Exception(sprintf("The class '%s' is not an AbstractDataSource", $dataSourceClass));
        }

        $this->dataSource = new $dataSourceClass($options);

        return $this;
    }

    public function handleRequest(Request $request): self
    {
        if (!$this->dataSource) {
            throw new \Exception("Impossible to handleRequest, the datatable has no associated dataSource.");
        }

        if ($this->request) {
            throw new \Exception("A request has already been handled.");
        }

        //Handle the filter request
        $this->request = $request;
        $this->filterForm?->handleRequest($request);

        $datatableSession = $this->requestStack->getSession()->get($this->getSessionName());

        $limitMax = (int)$this->getDatatableRequestParameter(self::LIMIT_MAX_PARAMETER_NAME);
        if (!in_array($limitMax, $this->getPossibleLimitMax(), true)) {
            $limitMax = (int)($datatableSession[self::DATATABLE_SESSION_LIMIT_NAME]['max'] ?? $this->limitValues['max']);
        }
        if (!in_array($limitMax, $this->getPossibleLimitMax(), true)) {
            $limitMax = $this->options['default_limit_max'];
        }

        //Reset the pagination if there is no limit, if the parameter is present or if the filter form has been submitted
        if (self::LIMIT_MAX_UNLIMITED === $limitMax || $this->request->get(self::RESET_PAGINATION_PARAMETER_NAME) == 1 || $this->filterForm?->get(AbstractBaseFilter::FILTER_SAVE_NAME)->isClicked()) {
            $offset = 0;
        } else {
            $offset = $this->getDatatableRequestParameter(self::LIMIT_OFFSET_PARAMETER_NAME);
            if (is_null($offset)) {
                $offset = $datatableSession[self::DATATABLE_SESSION_LIMIT_NAME]['offset'] ?? $this->limitValues['offset'];
            }
        }

        $this->limitValues = [
            'offset' => max((int)$offset, 0),
            'max' => $limitMax
        ];

        $sortArray = $this->getDatatableRequestParameter(self::SORT_PARAMETER_NAME);
        if (is_array($sortArray) && $sortArray) {
            $this->sortValues = [];
            foreach ($sortArray as $field => $direction) {
                if (isset($this->columns[$field])) {
                    $this->sortValues[] = ['field' => $this->columns[$field]->getOrderField(), 'field_name' => $field, 'direction' => $direction];
                }
            }
        } elseif (isset($datatableSession[self::DATATABLE_SESSION_SORT_NAME]) && !empty($datatableSession[self::DATATABLE_SESSION_SORT_NAME])) {
            $this->sortValues = $datatableSession[self::DATATABLE_SESSION_SORT_NAME];
        }

        //Remove the sort if the field no longer exists in the Datatable or if the direction is not a valid value
        foreach ($this->sortValues as $key => $value) {
            $direction = is_string($value['direction'] ?? null) ? strtolower($value['direction']) : null;

            if (!isset($value['field_name']) || !isset($this->columns[$value['field_name']])
                || !in_array($direction, [self::SORT_ORDER_ASCENDING, self::SORT_ORDER_DESCENDING], true)) {
                unset($this->sortValues[$key]);
                continue;
            }

            $this->sortValues[$key]['direction'] = $direction;
        }

        $datatableSession[self::DATATABLE_SESSION_LIMIT_NAME] = $this->limitValues;
        $datatableSession[self::DATATABLE_SESSION_SORT_NAME] = $this->sortValues;
        $this->requestStack->getSession()->set($this->getSessionName(), $datatableSession);

        $this->dataSource->initialize($this->columns, $this->sortValues, $this->limitValues, $this->filterForm);

        return $this;
    }

    public function isRequestHandled(): bool
    {
        return null !== $this->request;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPossibleLimitMax(): array
    {
        return $this->options['possible_limit_max'];
    }

    public function getUnlimitedLimitMax(): int
    {
        return self::LIMIT_MAX_UNLIMITED;
    }

    public function isLimitMaxUnlimited(): bool
    {
        return self::LIMIT_MAX_UNLIMITED === $this->getLimitMax();
    }

    public function getDisplayLimitSelector(): bool
    {
        return count($this->getPossibleLimitMax()) > 1;
    }

    public function getDisplayPagination(): bool
    {
        return !$this->isLimitMaxUnlimited();
    }

    public function getDisplayFooter(): bool
    {
        return $this->getDisplayLimitSelector() || $this->getDisplayPagination();
    }

    public function getCssClass(): ?string
    {
        return $this->options['css_class'];
    }

    public function getTemplate(): ?string
    {
        return $this->options['template'];
    }

    public function getTemplateTbody(): ?string
    {
        return $this->options['template_tbody'];
    }

    public function getResults(bool $forceRefresh = false): array
    {
        if (!$this->isRequestHandled()) {
            throw new \Exception("The datatable has not handled the request. Call handleRequest() before retrieving the results.");
        }

        return $this->dataSource->getResults($forceRefresh);
    }

    public function getFilteredCount(bool $forceRefresh = false): int
    {
        if (!$this->isRequestHandled()) {
            throw new \Exception("The datatable has not handled the request. Call handleRequest() before retrieving the filtered count.");
        }

        return $this->dataSource->getFilteredCount($forceRefresh);
    }

    public function getTotalCount(bool $forceRefresh = false): int
    {
        if (!$this->isRequestHandled()) {
            throw new \Exception("The datatable has not handled the request. Call handleRequest() before retrieving the total count.");
        }

        return $this->dataSource->getTotalCount($forceRefresh);
    }

    public function getLimitOffset(): int
    {
        return $this->limitValues['offset'];
    }

    public function setLimitOffset(int $offset): self
    {
        $this->limitValues['offset'] = $offset;
        if ($this->dataSource) {
            $this->dataSource->setLimitOffset($offset);
        }

        return $this;
    }

    public function getLimitMax(): int
    {
        return $this->limitValues['max'];
    }

    public function setLimitMax(int $max): self
    {
        $this->limitValues['max'] = $max;

        //There is only one page when there is no limit
        if (self::LIMIT_MAX_UNLIMITED === $max) {
            $this->setLimitOffset(0);
        }

        if ($this->dataSource) {
            $this->dataSource->setLimitMax($max);
        }

        return $this;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getFilterForm(): ?FormInterface
    {
        return $this->filterForm;
    }

    public function getDataSource(): ?AbstractDataSource
    {
        return $this->dataSource;
    }

    public function getScrollOnChange(): bool
    {
        return $this->options['scroll_on_change'];
    }

    public function getSessionName(): string
    {
        if ($this->name != self::DEFAULT_NAME) {
            return $this->name;
        }

        //Generate a name based on the current route, stripped of its last segment
        $routeParts = explode('_', (string)$this->requestStack->getCurrentRequest()?->get('_route'));
        if (count($routeParts) > 1) {
            array_pop($routeParts);
        }

        return $this->name . '-' . implode('_', $routeParts);
    }

    public function getDatatableRequestParameterName(string $parameter): mixed
    {
        return $this->getSessionName() . '[' . $parameter . ']';
    }

    protected function getDatatableRequestParameter(string $parameter): mixed
    {
        $dataTableRequest = $this->request->get($this->getSessionName());
        if ($dataTableRequest && isset($dataTableRequest[$parameter])) {
            return $dataTableRequest[$parameter];
        }

        return null;
    }
}