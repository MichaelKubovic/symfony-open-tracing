<?php

declare(strict_types=1);

namespace Adtechpotok\Bundle\SymfonyOpenTracing\Contract;

use Symfony\Component\Messenger\Envelope;

interface GetSpanNameByMessage
{
    public function getNameByMessage(Envelope $envelope): string;
}
