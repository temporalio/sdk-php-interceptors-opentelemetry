<?php

declare(strict_types=1);

namespace Temporal\OpenTelemetry\Tests\Unit\Interceptor;

use OpenTelemetry\API\Trace\SpanKind;
use Temporal\Client\WorkflowOptions;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Interceptor\HeaderInterface;
use Temporal\Interceptor\WorkflowClient\SignalWithStartInput;
use Temporal\Interceptor\WorkflowClient\StartInput;
use Temporal\OpenTelemetry\Enum\SpanName;
use Temporal\OpenTelemetry\Enum\WorkflowAttribute;
use Temporal\OpenTelemetry\Interceptor\OpenTelemetryWorkflowClientCallsInterceptor;
use Temporal\OpenTelemetry\Tests\Unit\TestCase;
use Temporal\Workflow\WorkflowExecution;

final class OpenTelemetryWorkflowClientCallsInterceptorTest extends TestCase
{
    private HeaderInterface $header;
    private StartInput $startInput;

    protected function setUp(): void
    {
        parent::setUp();

        $this->header = $this->createMock(HeaderInterface::class);

        $this->startInput = new StartInput(
            workflowId: 'someId',
            workflowType: 'someType',
            header: $this->header,
            arguments: $this->createMock(ValuesInterface::class),
            options: new WorkflowOptions(),
        );
    }

    public function testStart(): void
    {
        $this->header
            ->expects($this->once())
            ->method('withValue')
            ->with('_tracer-data', (object)[]);

        $tracer = $this->configureTracer(
            attributes: [
                WorkflowAttribute::Type->value => 'someType',
                WorkflowAttribute::RunId->value => 'someId',
                WorkflowAttribute::Header->value => []
            ],
            scoped: true,
            spanKind: SpanKind::KIND_CLIENT,
            name: SpanName::StartWorkflow->value
        );

        $interceptor = new OpenTelemetryWorkflowClientCallsInterceptor($tracer);
        $interceptor->start(
            $this->startInput,
            fn ($receivedInput): WorkflowExecution => new WorkflowExecution()
        );
    }

    public function testSignalWithStart(): void
    {
        $this->header
            ->expects($this->once())
            ->method('withValue')
            ->with('_tracer-data', (object)[]);

        $tracer = $this->configureTracer(
            attributes: [
                WorkflowAttribute::Type->value => 'someType',
                WorkflowAttribute::RunId->value => 'someId',
                WorkflowAttribute::Header->value => []
            ],
            scoped: true,
            spanKind: SpanKind::KIND_CLIENT,
            name: SpanName::SignalWithStartWorkflow->value . SpanName::SpanDelimiter->value . 'someType'
        );

        $input = new SignalWithStartInput(
            workflowStartInput: $this->startInput,
            signalName: 'someSignalName',
            signalArguments: $this->createMock(ValuesInterface::class)
        );

        $interceptor = new OpenTelemetryWorkflowClientCallsInterceptor($tracer);
        $interceptor->signalWithStart(
            $input,
            fn ($receivedInput): WorkflowExecution => new WorkflowExecution()
        );
    }
}
