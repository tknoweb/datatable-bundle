<?php

namespace Tknoweb\DatatableBundle\Utils;

use Symfony\Component\Routing\RouterInterface;

/**
 * Utility functions
 */
class Utils
{
    /**
     * Retrieve the route for a specific action inside a specific controller
     */
    public static function getRouteForControllerAndAction(string $controllerClass, string $actionName, RouterInterface $router): string
    {
        $controllerAction = $controllerClass . '::' . $actionName;

        foreach ($router->getRouteCollection() as $routeName => $route) {
            if ($route->getDefault('_controller') == $controllerAction) {
                return $routeName;
            }
        }

        throw new \Exception(sprintf("The action '%s' was not found for Controller '%s'", $actionName, $controllerClass));
    }
}