<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('core')]
class MappingEntity extends ArrayEntity
{
    #[\Override]
    public function getUniqueIdentifier(): string
    {
        $uniqueParameter = $this->getIdentifierFieldData();
        \ksort($uniqueParameter);

        return \implode('-', $uniqueParameter);
    }

    /**
     * @return non-empty-string[]
     */
    private function getIdentifierFieldData(): array
    {
        return \array_filter(
            $this->data,
            static fn (mixed $field): bool => \is_string($field) && Uuid::isValid($field),
        );
    }
}
