<?php

declare(strict_types=1);

namespace Temporal\OpenTelemetry\Enum;

enum SpanName: string
{
    case ActivityHandle = 'RunActivity';
    case StartWorkflow = 'StartWorkflow';
    case SignalWithStartWorkflow = 'SignalWithStartWorkflow';
    case WorkflowOutboundRequest = 'WorkflowOutboundRequest';
    case SpanDelimiter = ':';
}
