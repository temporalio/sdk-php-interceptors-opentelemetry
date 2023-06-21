<?php

declare(strict_types=1);

namespace Temporal\OpenTelemetry\Enum;

enum RequestAttribute: string
{
    case Type = 'request.type';
    case Name = 'request.name';
    case Id = 'request.id';
}
