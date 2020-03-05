<?php

declare(strict_types=1);

namespace Adtechpotok\Bundle\SymfonyOpenTracing\EventListener;

use Adtechpotok\Bundle\SymfonyOpenTracing\Contract\GetSpanNameByRequest;
use Adtechpotok\Bundle\SymfonyOpenTracing\Service\OpenTracingService;
use OpenTracing\Formats;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

class HttpListener
{
    /**
     * @var OpenTracingService
     */
    protected $openTracing;

    /**
     * @var GetSpanNameByRequest
     */
    protected $nameGetter;

    /**
     * @var string[]
     */
    private $skippedRoutes;

    /**
     * HttpListener constructor.
     *
     * @param OpenTracingService   $service
     * @param GetSpanNameByRequest $nameGetter
     * @param string[] $skippedRoutes
     */
    public function __construct(OpenTracingService $service, GetSpanNameByRequest $nameGetter, array $skippedRoutes = [])
    {
        $this->openTracing = $service;
        $this->nameGetter = $nameGetter;
        $this->skippedRoutes = $skippedRoutes;
    }

    /**
     * @param RequestEvent $event
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $tracer = $this->openTracing->getTracer();
        $request = $event->getRequest();

        $headers = [];
        foreach ($request->headers->all() as $key => $values) {
            $headers[$key] = is_array($values) ? $values[0] : $values;
        }
        $context = $tracer->extract(Formats\HTTP_HEADERS, $headers);

        if ($context) {
            $tracer->startActiveSpan($this->nameGetter->getNameByRequest($request), ['child_of' => $context]);
        } else {
            $tracer->startActiveSpan($this->nameGetter->getNameByRequest($request));
        }

        $tracer->getActiveSpan()->setTag('http.method', $event->getRequest()->getMethod());
        $tracer->getActiveSpan()->setTag('http.url', $event->getRequest()->getUri());
    }

    /**
     * @param ResponseEvent $event
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        $span = $this->openTracing->getTracer()->getActiveSpan();

        if ($span) {
            $headers = [];
            $this->openTracing->getTracer()->inject($span->getContext(), Formats\HTTP_HEADERS, $headers);
            $event->getResponse()->headers->add($headers);
        }
    }

    /**
     * @param TerminateEvent $event
     */
    public function onKernelTerminate(TerminateEvent $event): void
    {
        $span = $this->openTracing->getTracer()->getActiveSpan();

        if ($span) {
            $span->setTag('http.status_code', $event->getResponse()->getStatusCode());

            $span->finish();
        }

        $this->openTracing->getTracer()->flush();
    }

    /**
     * @param ExceptionEvent $event
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        $span = $this->openTracing->getTracer()->getActiveSpan();

        if ($span) {
            if ($event->hasResponse()) {
                $headers = [];
                $this->openTracing->getTracer()->inject($span->getContext(), Formats\HTTP_HEADERS, $headers);
                $event->getResponse()->headers->add($headers);

                $span->setTag('http.status_code', $event->getResponse()->getStatusCode());
            }

            $span->log([
                'error.kind'   => 'Exception',
                'error.object' => \get_class($event->getThrowable()),
                'message'      => $event->getThrowable()->getMessage(),
                'stack'        => $event->getThrowable()->getTraceAsString(),
            ]);

            $span->setTag('error', true);

            $span->finish();
        }

        $this->openTracing->getTracer()->flush();
    }

    private function skipRequest(Request $request): bool
    {
        return in_array($request->attributes->get('_route'), $this->skippedRoutes);
    }
}
