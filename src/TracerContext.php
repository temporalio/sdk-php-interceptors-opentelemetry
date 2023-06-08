<?php

declare(strict_types=1);

namespace Temporal\OpenTelemetry;

use Temporal\Api\Common\V1\Payload;
use Temporal\Interceptor\Header;
use Temporal\Interceptor\HeaderInterface;

trait TracerContext
{
    protected function getTracerHeader(): string
    {
        return '_tracer-data';
    }

    private function getTracerWithContext(HeaderInterface $header): ?Tracer
    {
        $tracerData = null;
        if ($header->toHeader()->getFields()->offsetExists($this->getTracerHeader())) {
            /** @var Payload $tracerData */
            $tracerData = $header->toHeader()->getFields()->offsetGet($this->getTracerHeader());
        }

        if ($tracerData === null) {
            return $this->tracer;
        }

        return $this->tracer->fromContext($this->converter->fromPayload($tracerData, 'array'));
    }

    private function setContext(HeaderInterface $header, array $context): HeaderInterface
    {
        $payloads = $header->toHeader()->getFields();

        $payload = $this->converter->toPayload((object)$context);

        $payloads[$this->getTracerHeader()] = $payload;

        return Header::fromPayloadCollection($payloads);
    }
}
