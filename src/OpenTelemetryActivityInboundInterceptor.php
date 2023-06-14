<?php

declare(strict_types=1);

namespace Temporal\OpenTelemetry;

use OpenTelemetry\API\Trace\SpanKind;
use Temporal\Activity;
use Temporal\Interceptor\ActivityInbound\ActivityInput;
use Temporal\Interceptor\ActivityInboundInterceptor;
use Temporal\Interceptor\Trait\ActivityInboundInterceptorTrait;

final class OpenTelemetryActivityInboundInterceptor implements ActivityInboundInterceptor
{
    use ActivityInboundInterceptorTrait, TracerContext;

    public function __construct(
        private readonly Tracer $tracer
    ) {
    }

    /**
     * @throws \Throwable
     */
    public function handleActivityInbound(ActivityInput $input, callable $next): mixed
    {
        $tracer = $this->getTracerWithContext($input->header);
        if ($tracer === null) {
            return $next($input);
        }

        return $tracer->trace(
            name: 'temporal.activity.handle',
            callback: static fn(): mixed => $next($input),
            attributes: [
                'activity.id' => Activity::getInfo()->id,
                'activity.attempt' => Activity::getInfo()->attempt,
                'activity.type' => Activity::getInfo()->type->name,
                'activity.task_queue' => Activity::getInfo()->taskQueue,
                'activity.workflow_type' => Activity::getInfo()->workflowType?->name,
                'activity.workflow_namespace' => Activity::getInfo()->workflowNamespace,
                'activity.header' => \iterator_to_array($input->header->getIterator()),
            ],
            scoped: true,
            spanKind: SpanKind::KIND_SERVER,
        );
    }
}
