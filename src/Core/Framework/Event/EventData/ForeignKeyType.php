<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventData;

use Shopware\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
class ForeignKeyType extends ScalarValueType
{
    public function __construct(
        private readonly string $referenceClass,
    ) {
        parent::__construct(self::TYPE_STRING);
    }

    public function getReferenceClass(): string
    {
        return $this->referenceClass;
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'referenceClass' => $this->referenceClass,
        ];
    }
}
