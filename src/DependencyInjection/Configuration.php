<?php declare(strict_types=1);

namespace Adtechpotok\Bundle\SymfonyOpenTracing\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{

    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('symfony_open_tracing');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        // Basic Sentry configuration
        $rootNode
            ->children()
                ->scalarNode('service_name')->end()
                ->scalarNode('enabled')->defaultTrue()->end()
                ->arrayNode('tracer_config')
                    ->children()
                        ->arrayNode('sampler')
                            ->children()
                                ->scalarNode('type')->defaultValue('const')->end()
                                ->scalarNode('param')->defaultTrue()->end()
                            ->end()
                        ->end()
                        ->arrayNode('local_agent')
                            ->children()
                                ->scalarNode('reporting_host')->defaultNull()->end()
                                ->scalarNode('reporting_port')->defaultNull()->end()
                            ->end()
                        ->end()
                        ->scalarNode('logging')->defaultTrue()->end()
                        ->scalarNode('trace_id_header')->defaultNull()->end()
                        ->scalarNode('baggage_header_prefix')->defaultNull()->end()
                        ->scalarNode('debug_id_header_key')->defaultNull()->end()
                    ->end()
                ->end()
                ->arrayNode('http_listener_skipped_routes')
                    ->beforeNormalization()->castToArray()->end()
                    ->prototype('scalar')->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
