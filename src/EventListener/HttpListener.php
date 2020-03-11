<?php

declare(strict_types=1);

namespace Adtechpotok\Bundle\SymfonyOpenTracing\EventListener;

use Adtechpotok\Bundle\SymfonyOpenTracing\Contract\GetSpanNameByRequest;
use OpenTracing\Formats;
use OpenTracing\Tracer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

class HttpListener
{
    /**
     * @var Tracer
     */
    protected $tracer;

    /**
     * @var GetSpanNameByRequest
     */
    protected $nameGetter;

    /**
     * @var array<string, bool>
     */
    private $skippedRoutes;

    /**
     * @param array<string, bool> $skippedRoutes route-indexed list of skipped routes
     */
    public function __construct(Tracer $tracer, GetSpanNameByRequest $nameGetter, array $skippedRoutes = [])
    {
        $this->tracer = $tracer;
        $this->nameGetter = $nameGetter;
        $this->skippedRoutes = $skippedRoutes;
    }

    /**
     * @param RequestEvent $event
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if ($this->skipRequest($request)) {
            return;
        }

        $headers = [];
        foreach ($request->headers->all() as $key => $values) {
            $headers[$key] = is_array($values) ? $values[0] : $values;
        }
        $context = $this->tracer->extract(Formats\HTTP_HEADERS, $headers);

        if ($context) {
            $this->tracer->startActiveSpan($this->nameGetter->getNameByRequest($request), ['child_of' => $context]);
        } else {
            $this->tracer->startActiveSpan($this->nameGetter->getNameByRequest($request));
        }

        $this->tracer->getActiveSpan()->setTag('http.method', $event->getRequest()->getMethod());
        $this->tracer->getActiveSpan()->setTag('http.url', $event->getRequest()->getUri());
    }

    /**
     * @param ResponseEvent $event
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if ($this->skipRequest($event->getRequest())) {
            return;
        }

        $span = $this->tracer->getActiveSpan();

        if ($span) {
            $headers = [];
            $this->tracer->inject($span->getContext(), Formats\HTTP_HEADERS, $headers);
            $event->getResponse()->headers->add($headers);
        }
    }

    /**
     * @param TerminateEvent $event
     */
    public function onKernelTerminate(TerminateEvent $event): void
    {
        $span = $this->tracer->getActiveSpan();

        if ($span) {
            $span->setTag('http.status_code', $event->getResponse()->getStatusCode());

            $span->finish();
        }

        $this->tracer->flush();
    }

    /**
     * @param ExceptionEvent $event
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        if ($this->skipRequest($event->getRequest())) {
            return;
        }

        $span = $this->tracer->getActiveSpan();

        if ($span) {
            if ($event->hasResponse()) {
                $headers = [];
                $this->tracer->inject($span->getContext(), Formats\HTTP_HEADERS, $headers);
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

        $this->tracer->flush();
    }

    private function skipRequest(Request $request): bool
    {
        return isset($this->skippedRoutes[$request->attributes->get('_route')]);
    }
}
