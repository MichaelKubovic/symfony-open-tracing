<?php

declare(strict_types=1);

namespace Adtechpotok\Bundle\SymfonyOpenTracing\Service;

use Jaeger\Config;
use OpenTracing\GlobalTracer;
use OpenTracing\NoopTracer;
use OpenTracing\Tracer;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class OpenTracingService
{
    use LoggerAwareTrait;

    /**
     * @var Tracer
     */
    protected $tracer;

    /**
     * @var bool
     */
    private $enabled;

    /**
     * @var string
     */
    private $appName;

    /**
     * @var array
     */
    private $config;

    /**
     * @var CacheItemPoolInterface
     */
    private $cache;

    public function __construct(bool $enabled, string $appName, array $config, CacheItemPoolInterface $cache)
    {
        $this->logger = new NullLogger();
        $this->enabled = $enabled;
        $this->appName = $appName;
        $this->config = $config;
        $this->cache = $cache;
    }

    public function getTracer(): Tracer
    {
        if ($this->tracer !== null) {
            return $this->tracer;
        }

        return $this->tracer = $this->initializeTracer();
    }

    private function initializeTracer()
    {
        if (!$this->enabled) {
            return new NoopTracer();
        }

        (new Config($this->config, $this->appName, $this->logger, $this->cache))->initializeTracer();

        return GlobalTracer::get();
    }
}
