<?php

declare(strict_types=1);

namespace Temporal\OpenTelemetry\Enum;

enum ActivityAttribute: string
{
    case Id = 'id';
    case Attempt ='attempt';
    case Type = 'activity_type';
    case TaskQueue = 'task_queue';
    case WorkflowType = 'workflow_type';
    case WorkflowNamespace = 'workflow_namespace';
    case Header = 'header';
}
