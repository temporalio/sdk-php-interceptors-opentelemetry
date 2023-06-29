# Temporal OpenTelemetry interceptors

## Introduction

The `temporal/open-telemetry-interceptors` package provides a set of OpenTelemetry interceptors for tracing workflows
and activities within the Temporal system using
the [OpenTelemetry SDK](https://opentelemetry.io/docs/instrumentation/php/).

These interceptors allow you to capture and trace various actions and events, such as handling activities, starting or
sending signals to workflows, and executing workflow events. By integrating OpenTelemetry tracing, you gain visibility
into the behavior and performance of your Temporal applications.

<img width="1511" alt="otel" src="https://github.com/temporalio/sdk-php-interceptors-opentelemetry/assets/67324318/615dd335-39df-4526-af71-7f422e39bfa9">


## Installation

To install the package, run the following command using Composer:

```bash
composer require temporal/open-telemetry-interceptors
```

## Usage

### Create a Pipeline Provider with Interceptors

First create a pipeline provider with the interceptors you want to use.

The following example shows how to create a pipeline provider with the `OpenTelemetryActivityInboundInterceptor`:

```php
use OpenTelemetry\SDK\Trace;
use Temporal\OpenTelemetry\Interceptor\OpenTelemetryActivityInboundInterceptor;
use Temporal\Interceptor\SimplePipelineProvider;

$spanProcessor = (new Trace\SpanProcessorFactory())->create(
    (new Trace\ExporterFactory())->create();
);

$tracerProvider = new Trace\TracerProvider($spanProcessor);
$propagator = Trace\Propagation\TraceContextPropagator::getInstance();

$tracer = new Temporal\OpenTelemetry\Tracer(
    $tracerProvider->getTracer('My super app'),
    $propagator
);

$activityInterceptor =  new OpenTelemetryActivityInboundInterceptor($tracer);

$provider = new SimplePipelineProvider([
     $activityInterceptor
]);
```

In this code snippet, we first create a span processor and a tracer provider using the OpenTelemetry SDK. We also
initialize a propagator for trace context propagation.

Next, we create an instance of the `Temporal\OpenTelemetry\Tracer` class, which wraps the OpenTelemetry tracer from the
tracer provider. We provide a unique name for our application and the propagator.

### Create a Workflow Client and Worker with Interceptors

Next, create a workflow client and worker with the interceptors you want to use.

```php
$client = new Temporal\Client\WorkflowClient(
    ..., 
    interceptorProvider: $provider
);

$worker = new WorkerFactory(
   ...,
   pipelineProvider: $provider
);
```

These steps ensure that the interceptors are applied to the respective client and worker instances, enabling tracing of
the desired actions and events.

## Interceptors

The package provides three types of interceptors:

### OpenTelemetryActivityInboundInterceptor

`Temporal\OpenTelemetry\Interceptor\OpenTelemetryActivityInboundInterceptor` traces the handling of activities
within workflows.

It captures and traces the following spans:

- `RunActivity`: Spans created when handling an activity.

### OpenTelemetryWorkflowClientCallsInterceptor

`Temporal\OpenTelemetry\Interceptor\OpenTelemetryWorkflowClientCallsInterceptor` traces the starting and signaling of workflows from
the client side. It captures spans with the names

- `StartWorkflow:<workflow type>`
- `SignalWithStartWorkflow:<workflow type>`.

These spans provide visibility into the initiation and signaling of workflows.

### OpenTelemetryWorkflowOutboundRequestInterceptor

`Temporal\OpenTelemetry\Interceptor\OpenTelemetryWorkflowOutboundRequestInterceptor` traces the execution of various workflow
events, including `ExecuteActivity`, `ExecuteLocalActivity`, `ExecuteChildWorkflow`, `ContinueAsNew`, `NewTimer`,
`CompleteWorkflow`, `SignalExternalWorkflow`, `CancelExternalWorkflow`, `GetVersion`, `Panic`, `SideEffect`,
`UpsertSearchAttributes`, and `Cancel`.

It captures spans with the name `WorkflowOutboundRequest:<event>`, providing detailed information
about outbound event requests.

## Available interceptor interfaces

Temporal SDK provides a collection of interfaces that allow you to implement interceptors for various actions and events
within the Temporal workflow and activity lifecycle. These interfaces enable you to customize and extend the behavior of
Temporal components by intercepting and modifying the execution flow.

### ActivityInboundInterceptor:

This interface defines the contract for intercepting the execution of activities within workflows.
Implementing this interface allows you to intercept when a workflow starts an activity and Temporal executes it.

- The `interceptActivityInbound()` method is invoked when an activity is executed within a workflow, providing the
  opportunity to intercept and modify the behavior.

By implementing this interface, you can add custom logic, perform validations, or apply additional functionality before
or after activity execution.

### WorkflowClientCallsInterceptor:

This interface defines the contract for intercepting client-side calls to start workflows or send signals.
Implementing this interface allows you to intercept workflow-related actions initiated from the client.

- The `interceptWorkflowClientCalls()` method is invoked when the client starts a workflow or sends a signal, providing
  the opportunity to intercept and modify the behavior.

By implementing this interface, you can add custom logic, perform pre-processing on inputs, or modify the workflow
execution based on specific conditions.

### WorkflowOutboundCallsInterceptor:

This interface defines the contract for intercepting various outbound workflow calls.
Implementing this interface allows you to intercept specific outbound calls made by workflows.

- The `interceptWorkflowOutboundCalls()` method is invoked when a workflow makes an outbound call, such as executing a
  local activity, executing a child workflow, or sending signals to external workflows.

By implementing this interface, you can intercept and modify the outbound calls, add custom behavior, or perform
additional operations before or after the execution of specific workflow events.

### WorkflowOutboundRequestInterceptor:

This interface defines the contract for intercepting outbound event requests made by workflows.
Implementing this interface allows you to intercept specific outbound event requests, such as executing activities,
continuing as new workflow, signaling external workflows, or canceling workflows.

- The `interceptWorkflowOutboundRequest()` method is invoked when a workflow makes an outbound event request, providing
  the opportunity to intercept and modify the request.

By implementing this interface, you can intercept and modify the outbound event requests, add custom metadata, modify
the payload, or perform additional operations before the request is sent.
