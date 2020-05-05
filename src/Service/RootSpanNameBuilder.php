<?php

declare(strict_types=1);

namespace Adtechpotok\Bundle\SymfonyOpenTracing\Service;

use Adtechpotok\Bundle\SymfonyOpenTracing\Contract\GetSpanNameByCommand;
use Adtechpotok\Bundle\SymfonyOpenTracing\Contract\GetSpanNameByMessage;
use Adtechpotok\Bundle\SymfonyOpenTracing\Contract\GetSpanNameByRequest;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use function end;
use function Symfony\Component\String\u;

class RootSpanNameBuilder implements GetSpanNameByRequest, GetSpanNameByCommand, GetSpanNameByMessage
{
    protected const ROUTE_NOT_FOUND = 'route_not_found';

    /**
     * @var string
     */
    protected $httpNamePrefix;

    /**
     * @var string
     */
    protected $cliNamePrefix;

    /**
     * @var string
     */
    private $messageNamePrefix;

    public function __construct(string $httpNamePrefix = 'http-tracing', string $cliNamePrefix = 'cli-tracing', string $messageNamePrefix = 'message-tracing')
    {
        $this->httpNamePrefix = $httpNamePrefix;
        $this->cliNamePrefix = $cliNamePrefix;
        $this->messageNamePrefix = $messageNamePrefix;
    }

    /**
     * @param Request $request
     *
     * @return string
     */
    public function getNameByRequest(Request $request): string
    {
        return sprintf('%s.%s', $this->httpNamePrefix, $request->attributes->get('_route', self::ROUTE_NOT_FOUND));
    }

    /**
     * @param Command $command
     *
     * @return string
     */
    public function getNameByCommand(Command $command): string
    {
        return sprintf('%s.%s', $this->cliNamePrefix, $command->getName());
    }

    public function getNameByMessage(Envelope $envelope): string
    {
        $classParts = explode('\\', get_class($envelope->getMessage()));
        $eventName = u(end($classParts))->camel();

        return sprintf('%s.%s', $this->messageNamePrefix, $eventName);
    }
}
