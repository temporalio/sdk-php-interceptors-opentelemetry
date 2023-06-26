<?php

declare(strict_types=1);

namespace Temporal\OpenTelemetry\Enum;

enum WorkflowAttribute: string
{
    case Type = 'workflow_type';
    case RunId = 'run_id';
    case Header = 'header';
}
