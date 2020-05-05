<?php

declare(strict_types=1);

namespace Adtechpotok\Bundle\SymfonyOpenTracing\EventListener;

use Adtechpotok\Bundle\SymfonyOpenTracing\Contract\GetSpanNameByMessage;
use OpenTracing\StartSpanOptions;
use OpenTracing\Tracer;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Stamp\BusNameStamp;

class MessengerListener
{
    /**
     * @var Tracer
     */
    protected $tracer;

    /**
     * @var GetSpanNameByMessage
     */
    private $nameGetter;

    public function __construct(Tracer $tracer, GetSpanNameByMessage $nameGetter)
    {
        $this->tracer = $tracer;
        $this->nameGetter = $nameGetter;
    }

    public function onWorkerMessageReceived(WorkerMessageReceivedEvent $event)
    {
        $envelope = $event->getEnvelope();
        $name = $this->nameGetter->getNameByMessage($envelope);
        $this->tracer->startActiveSpan($name, [
            'tags' => [
                'messenger.message' => get_class($envelope->getMessage()),
            ]
        ]);
    }

    public function onWorkerMessageHandled(WorkerMessageHandledEvent $event)
    {
        $span = $this->tracer->getActiveSpan();

        if ($span) {
            $span->setTag('messenger.transport', $event->getReceiverName());
            /** @var BusNameStamp $busStamp */
            $busStamp = $event->getEnvelope()->last(BusNameStamp::class);
            $span->setTag('messenger.bus', $busStamp->getBusName());
            $span->finish();
        }

        $this->tracer->flush();
    }

    public function onWorkerMessageFailed(WorkerMessageFailedEvent $event)
    {
        $span = $this->tracer->getActiveSpan();

        if ($span) {
            $span->log([
                'error.kind'   => 'Exception',
                'error.object' => \get_class($event->getThrowable()),
                'message'      => $event->getThrowable()->getMessage(),
                'stack'        => $event->getThrowable()->getTraceAsString(),
            ]);

            $span->setTag('error', true);

            $span->finish();
        }

        $this->tracer->flush();
    }
}
