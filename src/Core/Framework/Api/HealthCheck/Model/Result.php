<?php

namespace Shopware\Core\Framework\Api\HealthCheck\Model;

use InvalidArgumentException;

class Result
{
    public function __construct(
        private readonly string $name,
        private readonly Status $status = Status::Healthy,
        private readonly string $errorMessage = ''
    )
    {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function status(): Status
    {
        return $this->status;
    }

    public function healthy(): bool
    {
        return $this->status === Status::Healthy;
    }

    public function errorMessage(): string
    {
        return $this->errorMessage;
    }
}
