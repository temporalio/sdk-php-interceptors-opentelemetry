<?php

declare(strict_types=1);

namespace Temporal\OpenTelemetry;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;

final class Tracer
{
    private ?SpanInterface $lastSpan = null;

    public function __construct(
        private readonly TracerInterface $tracer,
        private readonly TextMapPropagatorInterface $propagator,
        private readonly array $context = []
    ) {
    }

    public function fromContext(array $context = []): self
    {
        $context = \array_intersect_ukey(
            $context,
            \array_flip($this->propagator->fields()),
            fn(string $key1, string $key2): int => (\strtolower($key1) === \strtolower($key2)) ? 0 : -1
        );

        return new self($this->tracer, $this->propagator, $context);
    }

    /**
     * @param non-empty-string $name
     * @psalm-param SpanKind::KIND_* $spanKind
     * @throws \Throwable
     */
    public function trace(
        string $name,
        callable $callback,
        array $attributes = [],
        bool $scoped = false,
        ?int $spanKind = null,
        ?int $startTime = null
    ): mixed {
        $traceSpan = $this->getTraceSpan($name, $spanKind, $startTime);

        $scope = null;
        if ($scoped) {
            $scope = $traceSpan->activate();
        }

        try {
            $result = $callback($traceSpan);

            $traceSpan->updateName($name);
            $traceSpan->setAttributes($attributes);

            return $result;
        } catch (\Throwable $e) {
            $traceSpan->recordException($e);
            throw $e;
        } finally {
            $traceSpan->end();
            $scope?->detach();
        }
    }

    public function getContext(): array
    {
        if ($this->lastSpan !== null) {
            $ctx = $this->lastSpan->storeInContext(Context::getCurrent());
            $carrier = [];
            $this->propagator->inject($carrier, null, $ctx);

            return $carrier;
        }

        return $this->context;
    }

    /**
     * @param non-empty-string $name
     * @psalm-param SpanKind::KIND_* $spanKind
     */
    private function getTraceSpan(
        string $name,
        ?int $spanKind,
        ?int $startTime
    ): SpanInterface {
        $spanBuilder = $this->tracer->spanBuilder($name);
        if ($spanKind !== null) {
            $spanBuilder->setSpanKind($spanKind);
        }

        if ($startTime !== null) {
            $spanBuilder->setStartTimestamp($startTime);
        }

        if ($this->context !== []) {
            $spanBuilder->setParent(
                $this->propagator->extract($this->context)
            );
        }

        return $this->lastSpan = $spanBuilder->startSpan();
    }

    public function getPropagator(): TextMapPropagatorInterface
    {
        return $this->propagator;
    }
}
