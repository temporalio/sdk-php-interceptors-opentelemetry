<?php

declare(strict_types=1);

namespace Temporal\OpenTelemetry;

use OpenTelemetry\API\Trace\SpanKind;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Interceptor\Trait\WorkflowClientCallsInterceptorTrait;
use Temporal\Interceptor\WorkflowClient\SignalWithStartInput;
use Temporal\Interceptor\WorkflowClient\StartInput;
use Temporal\Interceptor\WorkflowClientCallsInterceptor;
use Temporal\Workflow\WorkflowExecution;

final class OpenTelemetryWorkflowClientCallsInterceptor implements WorkflowClientCallsInterceptor
{
    use WorkflowClientCallsInterceptorTrait, TracerContext;

    public function __construct(
        private readonly Tracer $tracer,
        private readonly DataConverterInterface $converter,
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
            name: 'temporal.workflow.start',
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
            name: 'temporal.workflow.start_with_signal',
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
            'workflow.type' => $input->workflowType,
            'workflow.run_id' => $input->workflowId,
            'workflow.header' => \iterator_to_array($input->header->getIterator()),
        ];
    }
}
