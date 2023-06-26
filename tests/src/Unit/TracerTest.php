<?php

declare(strict_types=1);

namespace Temporal\OpenTelemetry\Tests\Unit;

use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Temporal\OpenTelemetry\Tracer;

final class TracerTest extends TestCase
{
    #[DataProvider('contextDataProvider')]
    public function testFromContext(array $context, array $expected): void
    {
        $tracer = new Tracer($this->createMock(TracerInterface::class), new TraceContextPropagator());

        $this->assertSame($expected, $tracer->fromContext($context)->getContext());
    }

    public function testTrace(): void
    {
        $tracer = $this->configureTracer();

        $this->assertSame(
            $this->span,
            $tracer->trace('foo', static fn (SpanInterface $span): SpanInterface => $span)
        );
    }

    public function testTraceWithAttributes(): void
    {
        $tracer = $this->configureTracer(attributes: ['foo' => 'bar']);

        $this->assertSame(
            $this->span,
            $tracer->trace(
                name: 'foo',
                callback: static fn (SpanInterface $span): SpanInterface => $span,
                attributes: ['foo' => 'bar']
            )
        );
    }

    public function testTraceScoped(): void
    {
        $tracer = $this->configureTracer(scoped: true);

        $this->assertSame(
            $this->span,
            $tracer->trace(
                name: 'foo',
                callback: static fn (SpanInterface $span): SpanInterface => $span,
                scoped: true
            )
        );
    }

    public function testTraceWithSpanKind(): void
    {
        $tracer = $this->configureTracer(spanKind: 5);

        $this->assertSame(
            $this->span,
            $tracer->trace(
                name: 'foo',
                callback: static fn (SpanInterface $span): SpanInterface => $span,
                spanKind: 5
            )
        );
    }

    public function testTraceWithStartTime(): void
    {
        $tracer = $this->configureTracer(startTime: 10);

        $this->assertSame(
            $this->span,
            $tracer->trace(
                name: 'foo',
                callback: static fn (SpanInterface $span): SpanInterface => $span,
                startTime: 10
            )
        );
    }

    public function testTraceWithException(): void
    {
        $span = $this->createMock(SpanInterface::class);
        $span
            ->expects($this->never())
            ->method('activate');
        $span
            ->expects($this->never())
            ->method('updateName');
        $span
            ->expects($this->never())
            ->method('setAttributes');
        $span
            ->expects($this->once())
            ->method('end');

        $spanBuilder = $this->createMock(SpanBuilderInterface::class);
        $spanBuilder
            ->expects($this->never())
            ->method('setSpanKind');
        $spanBuilder
            ->expects($this->never())
            ->method('setStartTimestamp');
        $spanBuilder
            ->expects($this->never())
            ->method('setParent');
        $spanBuilder
            ->expects($this->once())
            ->method('startSpan')
            ->willReturn($span);

        $otelTracer = $this->createMock(TracerInterface::class);
        $otelTracer
            ->expects($this->once())
            ->method('spanBuilder')
            ->with('foo')
            ->willReturn($spanBuilder);

        $tracer = new Tracer($otelTracer, new TraceContextPropagator());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Some error');
        $tracer->trace(
            name: 'foo',
            callback: static fn (SpanInterface $span): SpanInterface => throw new \Exception('Some error'),
        );
    }

    public function testGetPropagator(): void
    {
        $propagator = new TraceContextPropagator();
        $tracer = new Tracer($this->createMock(TracerInterface::class), $propagator);

        $this->assertSame($propagator, $tracer->getPropagator());
    }

    public static function contextDataProvider(): \Traversable
    {
        yield [[], []];
        yield [['foo' => 'bar'], []];
        yield [['traceparent' => 'foo', 'tracestate' => 'bar'], ['traceparent' => 'foo', 'tracestate' => 'bar']];
        yield [
            ['traceparent' => 'foo', 'tracestate' => 'bar', 'shouldBeRemoved' => 'value'],
            ['traceparent' => 'foo', 'tracestate' => 'bar']
        ];
    }
}
