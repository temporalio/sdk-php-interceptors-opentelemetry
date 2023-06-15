<?php

declare(strict_types=1);

namespace Temporal\OpenTelemetry\Interceptor;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SDK\Common\Time\ClockFactory;
use React\Promise\PromiseInterface;
use Temporal\Interceptor\WorkflowOutboundRequestInterceptor;
use Temporal\OpenTelemetry\Enum\RequestAttribute;
use Temporal\OpenTelemetry\Enum\SpanName;
use Temporal\OpenTelemetry\Enum\WorkflowAttribute;
use Temporal\OpenTelemetry\Tracer;
use Temporal\OpenTelemetry\TracerContext;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Workflow;

final class OpenTelemetryWorkflowOutboundRequestInterceptor implements WorkflowOutboundRequestInterceptor
{
    use TracerContext;

    public function __construct(
        private readonly Tracer $tracer
    ) {
    }

    /**
     * @throws \Throwable
     */
    public function handleOutboundRequest(RequestInterface $request, callable $next): PromiseInterface
    {
        $tracer = $this->getTracerWithContext($request->getHeader());
        if ($tracer === null) {
            return $next($request);
        }

        $now = ClockFactory::getDefault()->now();
        $type = Workflow::getInfo()->type;


        /** @var PromiseInterface $result */
        $result = $next($request);

        $trace = static fn(): mixed => $tracer->trace(
            name: SpanName::WorkflowOutboundRequest->value . SpanName::SpanDelimiter->value . $request->getName(),
            callback: static fn(): mixed => null,
            attributes: [
                RequestAttribute::Type->value => $request::class,
                RequestAttribute::Name->value => $request->getName(),
                RequestAttribute::Id->value => $request->getID(),
                WorkflowAttribute::Type->value => $type,
            ],
            scoped: true,
            spanKind: SpanKind::KIND_SERVER,
            startTime: $now,
        );

        return $result->then(
            onFulfilled: $trace,
            onRejected: $trace,
        );
    }
}
