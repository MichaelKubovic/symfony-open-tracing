<?php

declare(strict_types=1);

namespace Adtechpotok\Bundle\SymfonyOpenTracing\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
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

        if ($mergedConfig['enabled']) {
            $this->configureJaegerTracer($container, $mergedConfig);
        }

        $container
            ->getDefinition('open_tracing.http_listener')
            ->setArgument('$skippedRoutes', $mergedConfig['http_listener_skipped_routes'] ?? []);
    }

    private function configureJaegerTracer(ContainerBuilder $container, array $config)
    {
        $container
            ->getDefinition('jaeger.config')
            ->setArgument('$config', $config['tracer_config'])
            ->setArgument('$serviceName', $config['service_name'])
        ;

        $container->setAlias('open_tracing.tracer', 'jaeger.tracer');
    }
}
