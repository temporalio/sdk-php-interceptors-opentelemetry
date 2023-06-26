<?php

declare(strict_types=1);

namespace Temporal\OpenTelemetry\Tests\Unit\Interceptor;

use OpenTelemetry\API\Trace\SpanKind;
use Temporal\Activity;
use Temporal\Activity\ActivityContextInterface;
use Temporal\Activity\ActivityInfo;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Interceptor\ActivityInbound\ActivityInput;
use Temporal\Interceptor\HeaderInterface;
use Temporal\OpenTelemetry\Enum\ActivityAttribute;
use Temporal\OpenTelemetry\Enum\SpanName;
use Temporal\OpenTelemetry\Interceptor\OpenTelemetryActivityInboundInterceptor;
use Temporal\OpenTelemetry\Tests\Unit\TestCase;

final class OpenTelemetryActivityInboundInterceptorTest extends TestCase
{
    public function testHandleActivityInbound(): void
    {
        $info = new ActivityInfo();

        $ctx = $this->createMock(ActivityContextInterface::class);
        $ctx
            ->expects($this->exactly(6))
            ->method('getInfo')
            ->willReturn($info);

        Activity::setCurrentContext($ctx);

        $tracer = $this->configureTracer(
            attributes: [
                ActivityAttribute::Id->value => '0',
                ActivityAttribute::Attempt->value => 1,
                ActivityAttribute::Type->value => '',
                ActivityAttribute::TaskQueue->value => 'default',
                ActivityAttribute::WorkflowType->value => null,
                ActivityAttribute::WorkflowNamespace->value => 'default',
                ActivityAttribute::Header->value => []
            ],
            scoped: true,
            spanKind: SpanKind::KIND_SERVER,
            name: SpanName::ActivityHandle->value
        );

        $input = new ActivityInput(
            $this->createMock(ValuesInterface::class),
            $this->createMock(HeaderInterface::class),
        );

        $interceptor = new OpenTelemetryActivityInboundInterceptor($tracer);
        $interceptor->handleActivityInbound(
            $input,
            fn ($receivedInput): mixed => $this->assertSame($input, $receivedInput)
        );
    }
}
