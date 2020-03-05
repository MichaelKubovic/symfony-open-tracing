<?php

declare(strict_types=1);

namespace Adtechpotok\Bundle\SymfonyOpenTracing\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

class SymfonyOpenTracingExtension extends ConfigurableExtension
{
    /**
     * Configures the passed container according to the merged configuration.
     */
    protected function loadInternal(array $mergedConfig, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resource/config'));
        $loader->load('services.yml');

        $container
            ->getDefinition('open_tracing.tracer')
            ->setArguments([
                $mergedConfig['enabled'],
                $mergedConfig['service_name'],
                $mergedConfig['tracer_config'],
                new Reference('cache.app'),
            ]);

        $container
            ->getDefinition('open_tracing.http_listener')
            ->setArgument('$skippedRoutes', $mergedConfig['http_listener_skipped_routes'] ?? []);
    }
}
