<?php

declare(strict_types=1);

namespace Temporal\OpenTelemetry\Tests\Unit\Interceptor;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SDK\Common\Time\ClockFactory;
use OpenTelemetry\SDK\Common\Time\ClockInterface;
use React\Promise\PromiseInterface;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Interceptor\HeaderInterface;
use Temporal\OpenTelemetry\Enum\RequestAttribute;
use Temporal\OpenTelemetry\Enum\SpanName;
use Temporal\OpenTelemetry\Enum\WorkflowAttribute;
use Temporal\OpenTelemetry\Interceptor\OpenTelemetryWorkflowOutboundRequestInterceptor;
use Temporal\OpenTelemetry\Tests\Fixtures\Promise;
use Temporal\OpenTelemetry\Tests\Fixtures\Request;
use Temporal\OpenTelemetry\Tests\Unit\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowContextInterface;
use Temporal\Workflow\WorkflowInfo;

final class OpenTelemetryWorkflowOutboundRequestInterceptorTest extends TestCase
{
    public function testHandleOutboundRequest(): void
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock
            ->expects($this->once())
            ->method('now')
            ->willReturn(12345);

        ClockFactory::setDefault($clock);

        $info = new WorkflowInfo();
        $type = $info->type;

        $ctx = $this->createMock(WorkflowContextInterface::class);
        $ctx->expects($this->once())->method('getInfo')->willReturn($info);
        Workflow::setCurrentContext($ctx);

        $tracer = $this->configureTracer(
            attributes: [
                RequestAttribute::Type->value => Request::class,
                RequestAttribute::Name->value => 'someRequest',
                RequestAttribute::Id->value => 987,
                WorkflowAttribute::Type->value => $type
            ],
            scoped: true,
            spanKind: SpanKind::KIND_SERVER,
            startTime: 12345,
            name: SpanName::WorkflowOutboundRequest->value . SpanName::SpanDelimiter->value . 'someRequest'
        );

        $promise = new Promise();

        $interceptor = new OpenTelemetryWorkflowOutboundRequestInterceptor($tracer);
        $interceptor->handleOutboundRequest(
            new Request(
                $this->createMock(HeaderInterface::class),
                $this->createMock(ValuesInterface::class)
            ),
            fn ($receivedRequest): PromiseInterface => $promise
        );

        $callback = $promise->callback;
        $callback();
    }
}
