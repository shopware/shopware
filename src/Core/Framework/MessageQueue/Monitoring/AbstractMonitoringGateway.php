<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Monitoring;

abstract class AbstractMonitoringGateway
{
    abstract public function getDecorated(): self;

    abstract public function increment(string $name): void;

    abstract public function decrement(string $name): void;

    abstract public function reset(string $name): void;

    abstract public function get(): array;
}
