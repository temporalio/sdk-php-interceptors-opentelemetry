<?php

declare(strict_types=1);

namespace Temporal\OpenTelemetry\Enum;

enum RequestAttribute: string
{
    case Type = 'type';
    case Name = 'name';
    case Id = 'id';
}
