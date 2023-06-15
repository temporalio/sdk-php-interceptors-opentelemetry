<?php

declare(strict_types=1);

namespace Temporal\OpenTelemetry\Enum;

enum WorkflowAttribute: string
{
    case Type = 'workflow.type';
    case RunId = 'workflow.run_id';
    case Header = 'workflow.header';
}
