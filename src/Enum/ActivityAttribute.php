<?php

declare(strict_types=1);

namespace Temporal\OpenTelemetry\Enum;

enum ActivityAttribute: string
{
    case Id = 'activity.id';
    case Attempt ='activity.attempt';
    case Type = 'activity.type';
    case TaskQueue = 'activity.task_queue';
    case WorkflowType = 'activity.workflow_type';
    case WorkflowNamespace = 'activity.workflow_namespace';
    case Header = 'activity.header';
}
