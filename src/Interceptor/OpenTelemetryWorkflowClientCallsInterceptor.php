<?php

declare(strict_types=1);

namespace Temporal\OpenTelemetry\Interceptor;

use OpenTelemetry\API\Trace\SpanKind;
use Temporal\Interceptor\Trait\WorkflowClientCallsInterceptorTrait;
use Temporal\Interceptor\WorkflowClient\SignalWithStartInput;
use Temporal\Interceptor\WorkflowClient\StartInput;
use Temporal\Interceptor\WorkflowClientCallsInterceptor;
use Temporal\OpenTelemetry\Enum\SpanName;
use Temporal\OpenTelemetry\Enum\WorkflowAttribute;
use Temporal\OpenTelemetry\Tracer;
use Temporal\OpenTelemetry\TracerContext;
use Temporal\Workflow\WorkflowExecution;

final class OpenTelemetryWorkflowClientCallsInterceptor implements WorkflowClientCallsInterceptor
{
    use WorkflowClientCallsInterceptorTrait, TracerContext;

    public function __construct(
        private readonly Tracer $tracer
    ) {
    }

    /**
     * @throws \Throwable
     */
    public function start(StartInput $input, callable $next): WorkflowExecution
    {
        $tracer = $this->getTracerWithContext($input->header);

        if ($tracer === null) {
            return $next($input);
        }

        return $tracer->trace(
            name: SpanName::StartWorkflow->value . SpanName::SpanDelimiter->value . $input->workflowType,
            callback: fn(): mixed => $next(
                $input->with(
                    header: $this->setContext($input->header, $this->tracer->getContext()),
                ),
            ),
            attributes: $this->buildWorkflowAttributes($input),
            scoped: true,
            spanKind: SpanKind::KIND_CLIENT,
        );
    }

    /**
     * @throws \Throwable
     */
    public function signalWithStart(SignalWithStartInput $input, callable $next): WorkflowExecution
    {
        $startInput = $input->workflowStartInput;

        $tracer = $this->getTracerWithContext($startInput->header);
        if ($tracer === null) {
            return $next($input);
        }

        return $tracer->trace(
            name: SpanName::SignalWithStartWorkflow->value . SpanName::SpanDelimiter->value . $startInput->workflowType,
            callback: fn(): mixed => $next(
                $input->with(
                    workflowStartInput: $startInput->with(
                        header: $this->setContext($startInput->header, $this->tracer->getContext()),
                    ),
                ),
            ),
            attributes: $this->buildWorkflowAttributes($startInput),
            scoped: true,
            spanKind: SpanKind::KIND_CLIENT,
        );
    }

    private function buildWorkflowAttributes(StartInput $input): array
    {
        return [
            WorkflowAttribute::Type->value => $input->workflowType,
            WorkflowAttribute::RunId->value => $input->workflowId,
            WorkflowAttribute::Header->value => \iterator_to_array($input->header->getIterator()),
        ];
    }
}
