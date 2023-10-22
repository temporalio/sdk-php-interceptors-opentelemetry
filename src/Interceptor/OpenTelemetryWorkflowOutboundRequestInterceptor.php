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
        private readonly Tracer $tracer,
    ) {
    }

    /**
     * @throws \Throwable
     */
    public function handleOutboundRequest(RequestInterface $request, callable $next): PromiseInterface
    {
        $header = $request->getHeader();
        if ($header->getValue($this->getTracerHeader(), 'array') === null || Workflow::isReplaying()) {
            return $next($request);
        }

        $tracer = $this->getTracerWithContext($header);

        /** @var PromiseInterface $result */
        $result = $next($request);

        return $result->then(
            fn(mixed $value): mixed => $this->trace($tracer, $request, static fn(): mixed => $value),
            fn(\Throwable $error): mixed => $this->trace($tracer, $request, static function () use ($error): never {
                throw $error;
            }),
        );
    }

    /**
     * @throws \Throwable
     */
    private function trace(Tracer $tracer, RequestInterface $request, \Closure $handler): mixed
    {
        $now = ClockFactory::getDefault()->now();
        $type = Workflow::getInfo()->type;

        return $tracer->trace(
            name: SpanName::WorkflowOutboundRequest->value . SpanName::SpanDelimiter->value . $request->getName(),
            callback: $handler,
            attributes: [
                RequestAttribute::Type->value => $request::class,
                RequestAttribute::Name->value => $request->getName(),
                RequestAttribute::Id->value => $request->getID(),
                WorkflowAttribute::Type->value => $type->name,
            ],
            scoped: true,
            spanKind: SpanKind::KIND_SERVER,
            startTime: $now,
        );
    }
}
