<?php

declare(strict_types=1);

namespace Temporal\OpenTelemetry;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use Temporal\Exception\Failure\ApplicationErrorCategory;
use Temporal\Exception\Failure\ApplicationFailure;

/**
 * Wrapper for OpenTelemetry tracer to simplify trace span creation and context propagation.
 *
 * This class provides functionality to create trace spans with proper context handling,
 * enabling distributed tracing across workflow and activity boundaries.
 *
 * Usage example:
 * ```php
 *  $tracerProvider = new Trace\TracerProvider($spanProcessor);
 *  $tracer = new Tracer(
 *      $tracerProvider->getTracer('MyApp'),
 *      TraceContextPropagator::getInstance(),
 *  );
 *
 *  $result = $tracer->trace(
 *      name: 'operation_name',
 *      callback: fn() => performOperation(),
 *      attributes: ['key' => 'value'],
 *      scoped: true,
 *  );
 * ```
 */
final class Tracer
{
    /**
     * @var SpanInterface|null The last created span, used for context propagation
     */
    private ?SpanInterface $lastSpan = null;

    /**
     * @param TracerInterface $tracer The OpenTelemetry tracer implementation
     * @param TextMapPropagatorInterface $propagator Context propagator for distributed tracing
     * @param array<non-empty-string, mixed> $context Existing trace context for continued tracing
     */
    public function __construct(
        private readonly TracerInterface $tracer,
        private readonly TextMapPropagatorInterface $propagator,
        private readonly array $context = [],
    ) {}

    /**
     * Creates a new tracer with the specified context.
     *
     * Filters the provided context to only include fields recognized by the propagator.
     *
     * @param array<non-empty-string, mixed> $context The trace context to use
     * @return self A new tracer instance with the provided context
     */
    public function fromContext(array $context = []): self
    {
        $context = \array_intersect_ukey(
            $context,
            \array_flip($this->propagator->fields()),
            static fn(string $key1, string $key2): int => (\strtolower($key1) === \strtolower($key2)) ? 0 : -1,
        );

        return new self($this->tracer, $this->propagator, $context);
    }

    /**
     * Creates a trace span and executes the provided callback within its context.
     *
     * Handles span activation, attribute setting, exception recording, and proper cleanup.
     *
     * @param non-empty-string $name The name of the span
     * @param callable(SpanInterface): mixed $callback The function to execute within the span context
     * @param array<non-empty-string, array<array-key, mixed>|null|scalar> $attributes Span attributes to record
     * @param bool $scoped Whether to make this span the active span in the current context
     * @param SpanKind::KIND_*|null $spanKind The kind of span (client, server, etc.)
     * @param int|null $startTime Optional timestamp for when the span started
     * @return mixed The result of the callback execution
     * @throws \Throwable Any exception thrown by the callback is re-thrown after recording
     */
    public function trace(
        string $name,
        callable $callback,
        array $attributes = [],
        bool $scoped = false,
        ?int $spanKind = null,
        ?int $startTime = null,
    ): mixed {
        $traceSpan = $this->getTraceSpan($name, $spanKind, $startTime);

        $scope = null;
        if ($scoped) {
            $scope = $traceSpan->activate();
        }

        $traceSpan->updateName($name);
        $traceSpan->setAttributes($this->normalizeAttributes($attributes));

        try {
            $traceSpan->setStatus(StatusCode::STATUS_OK);

            return $callback($traceSpan);
        } catch (\Throwable $e) {
            $traceSpan->recordException($e);
            if (!$this->canSkipExceptionRecording($e)) {
                $traceSpan->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            }
            throw $e;
        } finally {
            $traceSpan->end();
            $scope?->detach();
        }
    }

    /**
     * Gets the current tracing context for propagation.
     *
     * If a span has been created by this tracer, uses that span's context.
     * Otherwise, returns the original context.
     *
     * @return array<non-empty-string, mixed> The trace context for propagation
     */
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
     * Gets the text map propagator used by this tracer.
     *
     * @return TextMapPropagatorInterface The propagator instance
     */
    public function getPropagator(): TextMapPropagatorInterface
    {
        return $this->propagator;
    }

    /**
     * Convert mixed values to scalar or null.
     *
     * @param iterable<non-empty-string, mixed> $attributes
     *
     * @return iterable<non-empty-string, null|scalar|array<array-key, null|scalar>>
     */
    private function normalizeAttributes(iterable $attributes): iterable
    {
        $normalized = [];
        foreach ($attributes as $key => $value) {
            $normalized[$key] = match (true) {
                !\is_array($value) => self::normalizeAttributeValue($value),
                \array_keys($value) === [0] => self::normalizeAttributeValue($value[0]),
                default => \array_map(self::normalizeAttributeValue(...), $value),
            };
        }

        return $normalized;
    }

    /**
     * Convert a single value to scalar or null.
     */
    private function normalizeAttributeValue(mixed $value): null|bool|int|float|string
    {
        return match (true) {
            $value === null || \is_scalar($value) => $value,
            $value instanceof \Stringable => $value->__toString(),
            \is_array($value) || $value instanceof \stdClass || $value instanceof \JsonSerializable => \json_encode($value),
            \is_object($value) => $value::class,
            default => \get_debug_type($value),
        };
    }

    /**
     * {@see ApplicationFailure} could be thrown from userland
     * As well as it could come from the Temporal SDK itself
     */
    private function canSkipExceptionRecording(\Throwable $e): bool
    {
        if ($e instanceof ApplicationFailure) {
            return $e->getApplicationErrorCategory() === ApplicationErrorCategory::Benign;
        }
        if ($e->getPrevious() instanceof ApplicationFailure) {
            return $e->getPrevious()->getApplicationErrorCategory() === ApplicationErrorCategory::Benign;
        }
        return false;
    }

    /**
     * Creates a new trace span with the given parameters.
     *
     * Configures the span with the appropriate kind, start time, and parent context.
     * Stores the created span as the last span for context propagation.
     *
     * @param non-empty-string $name The name of the span
     * @param SpanKind::KIND_*|null $spanKind The kind of span (client, server, etc.)
     * @param int|null $startTime Optional timestamp for when the span started
     * @return SpanInterface The created span instance
     */
    private function getTraceSpan(
        string $name,
        ?int $spanKind,
        ?int $startTime,
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
                $this->propagator->extract($this->context),
            );
        }

        return $this->lastSpan = $spanBuilder->startSpan();
    }
}
