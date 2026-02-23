<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventData;

use Shopware\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
abstract class AbstractEventDataType implements EventDataType
{
    private bool $nullable = false;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'nullable' => $this->nullable,
        ];
    }

    public function setNullable(): self
    {
        $this->nullable = true;

        return $this;
    }
}
