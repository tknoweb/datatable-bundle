<?php

namespace Tknoweb\DatatableBundle\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Tknoweb\DatatableBundle\Datatable\Datatable;
use Tknoweb\DatatableBundle\Utils\Utils;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class DatatableExtension extends AbstractExtension
{
    protected ?string $currentController = null;
    protected array $currentControllerMatchedRoutes = [];

    public function __construct(protected Environment $twig, protected RequestStack $requestStack, protected RouterInterface $router)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('renderDatatable', [$this, 'renderDatatable']),
            new TwigFunction('pathForCurrentControllerAction', [$this, 'generatePathForCurrentControllerAction']),
            new TwigFunction('currentUrlWithParameters', [$this, 'generateCurrentUrlWithParameters']),
            new TwigFunction('inverseColumnSort', [$this, 'getInverseColumnSort']),
            new TwigFunction('datatableTurboFrameId', [$this, 'getDatatableTurboFrameId']),
        ];
    }

    /**
     * Display the full datatable content
     */
    public function renderDatatable(Datatable $datatable): void
    {
        if (!$datatable->isRequestHandled()) {
            throw new \Exception("The datatable has not handled the request. Call handleRequest() before rendering it.");
        }

        $this->twig->display($datatable->getTemplate() ?? Datatable::DEFAULT_TEMPLATE, ['datatable' => $datatable]);
    }

    /**
     * Generate a path for a specific action inside the current controller
     */
    public function generatePathForCurrentControllerAction(string $action, array $parameters = []): string
    {
        if (!$this->currentController) {
            $controllerFullName = $this->requestStack->getCurrentRequest()->get('_controller');
            $this->currentController = explode('::', $controllerFullName)[0];
        }

        $controllerAction = $this->currentController . '::' . $action;
        if (isset($this->currentControllerMatchedRoutes[$controllerAction])) {
            return $this->router->generate($this->currentControllerMatchedRoutes[$controllerAction], $parameters);
        }

        $path = Utils::getRouteForControllerAndAction($this->currentController, $action, $this->router);
        $this->currentControllerMatchedRoutes[$controllerAction] = $path;

        return $this->router->generate($path, $parameters);
    }

    /**
     * Return the current URL with specific parameters
     */
    public function generateCurrentUrlWithParameters(array $parameters): string
    {
        $basePath = $this->requestStack->getCurrentRequest()->getPathInfo();

        return $basePath . '?' . http_build_query($parameters);
    }

    /**
     * Get the inverse of a column sort
     */
    public function getInverseColumnSort(?string $sort): string
    {
        if ($sort == Datatable::SORT_ORDER_ASCENDING) {
            return Datatable::SORT_ORDER_DESCENDING;
        } else if ($sort == Datatable::SORT_ORDER_DESCENDING) {
            return Datatable::SORT_ORDER_ASCENDING;
        } else {
            return Datatable::SORT_ORDER_ASCENDING;
        }
    }

    /**
     * Get the datatable turbo frame ID
     */
    public function getDatatableTurboFrameId(Datatable $datatable): string
    {
        return "datatable-frame-" . $datatable->getName();
    }
}