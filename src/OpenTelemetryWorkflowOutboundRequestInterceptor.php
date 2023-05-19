<?php

declare(strict_types=1);

namespace Temporal\OpenTelemetry;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use OpenTelemetry\SDK\Common\Time\ClockFactory;
use React\Promise\PromiseInterface;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Interceptor\WorkflowOutboundRequestInterceptor;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Workflow;

final class OpenTelemetryWorkflowOutboundRequestInterceptor implements WorkflowOutboundRequestInterceptor
{
    use TracerContext;

    private readonly TextMapPropagatorInterface $propagator;

    public function __construct(
        private readonly Tracer $tracer,
        private readonly DataConverterInterface $converter,
    ) {
        $this->propagator = $tracer->getPropagator();
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

        $trace = static fn() => $tracer->trace(
            name: 'temporal.workflow.outbound.request.' . $request->getName(),
            callback: static fn(): mixed => null,
            attributes: [
                'request.type' => $request::class,
                'request.name' => $request->getName(),
                'request.id' => $request->getID(),
                'workflow.type' => $type,
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