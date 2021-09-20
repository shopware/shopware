<?php

namespace Shopware\Core\Framework\MessageQueue\Monitoring;

use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

class ArrayMonitoringGateway extends AbstractMonitoringGateway
{
    private array $logs = [];

    public function getDecorated(): AbstractMonitoringGateway
    {
        throw new DecorationPatternException(self::class);
    }

    public function increment(string $name): void
    {
        if (!array_key_exists($name, $this->logs)) {
            $this->logs[$name] = 0;
        }
        $this->logs[$name]++;
    }

    public function decrement(string $name): void
    {
        if (!array_key_exists($name, $this->logs)) {
            $this->logs[$name] = 0;
        }

        $this->logs[$name]--;
    }

    public function reset(string $name): void
    {
        $this->logs[$name] = 0;
    }

    public function get(): array
    {
        $mapped = [];
        foreach ($this->logs as $key => $size) {
            $mapped[$key] = ['name' => $key, 'size' => $size];
        }

        return $mapped;
    }
}
