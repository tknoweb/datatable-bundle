<?php

namespace Tknoweb\DatatableBundle;

use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class TknowebDatatableBundle extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.yml');

        $container->parameters()->set('datatable.column', $config['column']);
        $container->parameters()->set('datatable.datatable', $config['datatable']);
        $container->parameters()->set('datatable.action', $config['action']);
        $container->parameters()->set('datatable.action_column', $config['action_column']);
    }

    /**
     * Extra configuration needs to be prepend
     */
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $containerBuilder): void
    {
        $container->import('../config/packages/twig.yml');

        if (!$this->isAssetMapperAvailable($containerBuilder)) {
            return;
        }

        $containerBuilder->prependExtensionConfig('framework', [
            'asset_mapper' => [
                'paths' => [
                    __DIR__ . '/../assets' => '@tknoweb/datatable-bundle',
                ],
                'excluded_patterns' => [
                    '*/datatable-bundle/assets/package.json',
                ],
            ],
        ]);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('column')
                    ->info('Parameters to be passed to the columns')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('route')
                            ->info('The route to redirect when clicking a column cell')
                        ->end()
                        ->arrayNode('route_parameters')
                            ->info('The parameters passed to the route when clicking a column cell')
                            ->arrayPrototype()
                                ->children()
                                ->end()
                            ->end()
                        ->end()
                        ->scalarNode('head_css_class')
                            ->info('Css class to add to the table th')
                        ->end()
                        ->scalarNode('cell_css_class')
                            ->info('Css class to add to the table td')
                        ->end()
                        ->scalarNode('bool_text_true')
                            ->info('Default text when a bool column is true')
                            ->defaultValue('Oui')
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('bool_text_false')
                            ->info('Default text when a bool column is false')
                            ->defaultValue('Non')
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('date_time_format')
                            ->info('Default formatting for datetime column')
                            ->defaultValue('d/m/Y H:i:s')
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('date_format')
                            ->info('Default formatting for date column')
                            ->defaultValue('d/m/Y')
                            ->cannotBeEmpty()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('action_column')
                ->info('Parameters to be passed to the action column')
                    ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('template_style')
                            ->info('The default action template layout, either inline or grouped')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('action')
                    ->info('Parameters to be passed to the actions')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('css_class')
                            ->info('Default css class for the actions')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('datatable')
                    ->info('Parameters to be passed to the datatable')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('css_class')
                            ->info('Default css classes for the datatable')
                        ->end()
                        ->integerNode('default_limit_max')
                            ->info('Default maximum results for limit clause, -1 to display every result')
                            ->defaultValue(25)
                        ->end()
                        ->arrayNode('possible_limit_max')
                            ->info('An array of possible limit values as integer, -1 being the value to display every result')
                            ->defaultValue([10,25,50])
                            ->requiresAtLeastOneElement()
                            ->prototype('integer')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ->end()
        ;
    }

    private function isAssetMapperAvailable(ContainerBuilder $containerBuilder): bool
    {
        if (!interface_exists(AssetMapperInterface::class)) {
            return false;
        }

        // check that FrameworkBundle 6.3 or higher is installed
        $bundlesMetadata = $containerBuilder->getParameter('kernel.bundles_metadata');
        if (!isset($bundlesMetadata['FrameworkBundle'])) {
            return false;
        }

        return is_file($bundlesMetadata['FrameworkBundle']['path'] . '/Resources/config/asset_mapper.php');
    }
}