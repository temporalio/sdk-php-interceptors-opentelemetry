<?php

declare(strict_types=1);

namespace Temporal\OpenTelemetry\Enum;

enum SpanName: string
{
    case ActivityHandle = 'temporal.activity.handle';
    case StartWorkflow = 'StartWorkflow';
    case SignalWithStartWorkflow = 'SignalWithStartWorkflow';
    case WorkflowOutboundRequest = 'temporal.workflow.outbound.request';
    case SpanDelimiter = ':';
}
