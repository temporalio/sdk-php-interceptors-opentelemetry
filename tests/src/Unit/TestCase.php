<?php

declare(strict_types=1);

namespace Temporal\OpenTelemetry\Tests\Unit;

use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\ScopeInterface;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Temporal\OpenTelemetry\Tracer;

abstract class TestCase extends PHPUnitTestCase
{
    protected SpanInterface $span;
    protected SpanBuilderInterface $spanBuilder;

    protected function setUp(): void
    {
        $this->span = $this->createMock(SpanInterface::class);
        $this->spanBuilder = $this->createMock(SpanBuilderInterface::class);
    }

    protected function configureTracer(
        array $attributes = [],
        bool $scoped = false,
        ?int $spanKind = null,
        ?int $startTime = null,
        string $name = 'foo',
    ): Tracer {
        if ($scoped) {
            $scope = $this->createMock(ScopeInterface::class);
            $scope
                ->expects($this->once())
                ->method('detach');

            $this->span
                ->expects($this->once())
                ->method('activate')
                ->willReturn($scope);
        } else {
            $this->span->expects($this->never())->method('activate');
        }
        $this->span->expects($this->once())->method('updateName')->with($name);
        $this->span->expects($this->once())->method('setAttributes')->with($attributes);
        $this->span->expects($this->once())->method('end');

        $spanKind !== null
            ? $this->spanBuilder->expects($this->once())->method('setSpanKind')->with($spanKind)
            : $this->spanBuilder->expects($this->never())->method('setSpanKind');

        $startTime !== null
            ? $this->spanBuilder->expects($this->once())->method('setStartTimestamp')->with($startTime)
            : $this->spanBuilder->expects($this->never())->method('setStartTimestamp');
        $this->spanBuilder->expects($this->never())->method('setParent');
        $this->spanBuilder->expects($this->once())->method('startSpan')->willReturn($this->span);

        $otelTracer = $this->createMock(TracerInterface::class);
        $otelTracer
            ->expects($this->once())
            ->method('spanBuilder')
            ->with($name)
            ->willReturn($this->spanBuilder);

        return new Tracer($otelTracer, new TraceContextPropagator());
    }

    protected function configureTracerToRegisterException(
        bool $scoped = false,
        ?int $spanKind = null,
        ?int $startTime = null,
        string $name = 'foo',
        ?\Throwable $exception = null,
    ): Tracer {
        if ($scoped) {
            $scope = $this->createMock(ScopeInterface::class);
            $scope
                ->expects($this->once())
                ->method('detach');

            $this->span
                ->expects($this->once())
                ->method('activate')
                ->willReturn($scope);
        } else {
            $this->span->expects($this->never())->method('activate');
        }
        $this->span->expects($this->once())->method('end');
        $exception === null
            ? $this->span->expects($this->once())->method('recordException')
            : $this->span->expects($this->once())->method('recordException')->with($exception);

        $spanKind !== null
            ? $this->spanBuilder->expects($this->once())->method('setSpanKind')->with($spanKind)
            : $this->spanBuilder->expects($this->never())->method('setSpanKind');

        $startTime !== null
            ? $this->spanBuilder->expects($this->once())->method('setStartTimestamp')->with($startTime)
            : $this->spanBuilder->expects($this->never())->method('setStartTimestamp');
        $this->spanBuilder->expects($this->never())->method('setParent');
        $this->spanBuilder->expects($this->once())->method('startSpan')->willReturn($this->span);

        $otelTracer = $this->createMock(TracerInterface::class);
        $otelTracer
            ->expects($this->once())
            ->method('spanBuilder')
            ->with($name)
            ->willReturn($this->spanBuilder);

        return new Tracer($otelTracer, new TraceContextPropagator());
    }
}
