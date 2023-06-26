<?php

declare(strict_types=1);

namespace Temporal\OpenTelemetry\Interceptor;

use OpenTelemetry\API\Trace\SpanKind;
use Temporal\Activity;
use Temporal\Interceptor\ActivityInbound\ActivityInput;
use Temporal\Interceptor\ActivityInboundInterceptor;
use Temporal\Interceptor\Trait\ActivityInboundInterceptorTrait;
use Temporal\OpenTelemetry\Enum\ActivityAttribute;
use Temporal\OpenTelemetry\Enum\SpanName;
use Temporal\OpenTelemetry\Tracer;
use Temporal\OpenTelemetry\TracerContext;

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
            name: SpanName::ActivityHandle->value,
            callback: static fn(): mixed => $next($input),
            attributes: [
                ActivityAttribute::Id->value => Activity::getInfo()->id,
                ActivityAttribute::Attempt->value => Activity::getInfo()->attempt,
                ActivityAttribute::Type->value => Activity::getInfo()->type->name,
                ActivityAttribute::TaskQueue->value => Activity::getInfo()->taskQueue,
                ActivityAttribute::WorkflowType->value => Activity::getInfo()->workflowType?->name,
                ActivityAttribute::WorkflowNamespace->value => Activity::getInfo()->workflowNamespace,
                ActivityAttribute::Header->value => \iterator_to_array($input->header->getIterator()),
            ],
            scoped: true,
            spanKind: SpanKind::KIND_SERVER,
        );
    }
}
