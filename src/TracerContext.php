<?php

declare(strict_types=1);

namespace Temporal\OpenTelemetry;

use Temporal\Interceptor\HeaderInterface;

trait TracerContext
{
    protected function getTracerHeader(): string
    {
        return '_tracer-data';
    }

    private function getTracerWithContext(HeaderInterface $header): Tracer
    {
        $tracerData = $header->getValue($this->getTracerHeader(), 'array');

        return $tracerData === null ? $this->tracer : $this->tracer->fromContext($tracerData);
    }

    private function setContext(HeaderInterface $header, array $context): HeaderInterface
    {
        return $header->withValue($this->getTracerHeader(), (object)$context);
    }
}
