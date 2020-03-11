<?php

declare(strict_types=1);

namespace Adtechpotok\Bundle\SymfonyOpenTracing\EventListener;

use Adtechpotok\Bundle\SymfonyOpenTracing\Contract\GetSpanNameByCommand;
use OpenTracing\Tracer;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;

class CliListener
{
    /**
     * @var Tracer
     */
    protected $tracer;

    /**
     * @var GetSpanNameByCommand
     */
    protected $nameGetter;

    public function __construct(Tracer $tracer, GetSpanNameByCommand $nameGetter)
    {
        $this->tracer = $tracer;
        $this->nameGetter = $nameGetter;
    }

    /**
     * @param ConsoleCommandEvent $event
     */
    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $name = $this->nameGetter->getNameByCommand($event->getCommand());

        $this->tracer->startActiveSpan($name);
    }

    /**
     * @param ConsoleTerminateEvent $event
     */
    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        $span = $this->tracer->getActiveSpan();

        if ($span) {
            $span->finish();
        }

        $this->tracer->flush();
    }

    /**
     * @param ConsoleErrorEvent $event
     */
    public function onConsoleError(ConsoleErrorEvent $event): void
    {
        $span = $this->tracer->getActiveSpan();

        if ($span) {
            $span->log([
                'error.kind'   => 'Error',
                'error.object' => \get_class($event->getError()),
                'message'      => $event->getError()->getMessage(),
                'stack'        => $event->getError()->getTraceAsString(),
            ]);
            $span->setTag('error', true);
        }
    }
}
