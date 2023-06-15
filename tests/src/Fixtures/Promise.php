<?php

declare(strict_types=1);

namespace Temporal\OpenTelemetry\Tests\Fixtures;

use React\Promise\PromiseInterface;

final class Promise implements PromiseInterface
{
    public mixed $callback;

    public function then(
        callable $onFulfilled = null,
        callable $onRejected = null,
        callable $onProgress = null
    ): PromiseInterface {
        $this->callback = $onFulfilled;

        return $this;
    }
}
