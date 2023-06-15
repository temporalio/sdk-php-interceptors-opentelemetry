<?php

declare(strict_types=1);

namespace Temporal\OpenTelemetry\Tests\Fixtures;

use Temporal\DataConverter\ValuesInterface;
use Temporal\Interceptor\HeaderInterface;
use Temporal\Worker\Transport\Command\RequestInterface;

final class Request implements RequestInterface
{
    public function __construct(
        private readonly HeaderInterface $header,
        private readonly ValuesInterface $values
    ) {
    }

    public function getID(): int
    {
        return 987;
    }

    public function getHeader(): HeaderInterface
    {
        return $this->header;
    }

    public function getName(): string
    {
        return 'someRequest';
    }

    public function getOptions(): array
    {
        return [];
    }

    public function getPayloads(): ValuesInterface
    {
        return $this->values;
    }

    public function getFailure(): ?\Throwable
    {
        return null;
    }

    public function withHeader(HeaderInterface $header): RequestInterface
    {
        return $this;
    }
}
