<?php

declare(strict_types=1);

namespace Temporal\OpenTelemetry\Tests\Unit\Interceptor;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SDK\Common\Time\ClockFactory;
use OpenTelemetry\SDK\Common\Time\ClockInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use stdClass;
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
        $info->type->name = 'foo';

        $ctx = $this->createMock(WorkflowContextInterface::class);
        $ctx->expects($this->once())->method('getInfo')->willReturn($info);
        Workflow::setCurrentContext($ctx);

        $tracer = $this->configureTracer(
            attributes: [
                RequestAttribute::Type->value => Request::class,
                RequestAttribute::Name->value => 'someRequest',
                RequestAttribute::Id->value => 987,
                WorkflowAttribute::Type->value => 'foo'
            ],
            scoped: true,
            spanKind: SpanKind::KIND_SERVER,
            startTime: 12345,
            name: SpanName::WorkflowOutboundRequest->value . SpanName::SpanDelimiter->value . 'someRequest'
        );

        $deferred = new Deferred();
        $promise = $deferred->promise();
        $testContext = (object)['value' => null, 'error' => null];

        $header = $this->createMock(HeaderInterface::class);
        $header
            ->method('getValue')
            ->with('_tracer-data')
            ->willReturn(['some' => 'data']);

        $interceptor = new OpenTelemetryWorkflowOutboundRequestInterceptor($tracer);
        $interceptor->handleOutboundRequest(
            new Request(
                $header,
                $this->createMock(ValuesInterface::class)
            ),
            fn ($receivedRequest): PromiseInterface => $promise
        )->then(
            fn ($value) => $testContext->value = $value,
            fn ($error) => $testContext->error = $error,
        );

        $deferred->resolve('test-value');

        // test that the promise is resolved with the correct value
        $this->assertSame($testContext->value, 'test-value');
        $this->assertNull($testContext->error);
    }

    public function testHandleOutboundRequestWithError(): void
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock
            ->expects($this->once())
            ->method('now')
            ->willReturn(12345);

        ClockFactory::setDefault($clock);

        $info = new WorkflowInfo();
        $info->type->name = 'foo';

        $ctx = $this->createMock(WorkflowContextInterface::class);
        $ctx->expects($this->once())->method('getInfo')->willReturn($info);
        Workflow::setCurrentContext($ctx);
        $exception = new \Exception('test-error');

        $tracer = $this->configureTracerToRegisterException(
            scoped: true,
            spanKind: SpanKind::KIND_SERVER,
            startTime: 12345,
            name: SpanName::WorkflowOutboundRequest->value . SpanName::SpanDelimiter->value . 'someRequest',
            exception: $exception,
        );

        $deferred = new Deferred();
        $promise = $deferred->promise();
        $testContext = (object)['value' => null, 'error' => null];

        $header = $this->createMock(HeaderInterface::class);
        $header
            ->method('getValue')
            ->with('_tracer-data')
            ->willReturn(['some' => 'data']);

        $interceptor = new OpenTelemetryWorkflowOutboundRequestInterceptor($tracer);
        $interceptor->handleOutboundRequest(
            new Request(
                $header,
                $this->createMock(ValuesInterface::class)
            ),
            fn ($receivedRequest): PromiseInterface => $promise
        )->then(
            fn (mixed $value) => $testContext->value = $value,
            fn (\Throwable $error) => $testContext->error = $error,
        );

        $deferred->reject($exception);

        // test that the promise is rejected with the correct error
        $this->assertSame($exception, $testContext->error);
        $this->assertNull($testContext->value);
    }
}
