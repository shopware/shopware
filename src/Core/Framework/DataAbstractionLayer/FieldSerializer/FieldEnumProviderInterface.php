<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

interface FieldEnumProviderInterface
{
    public function isSupported(string $entity, string $fieldName): bool;

    /**
     * @return array<string|bool|int|float>
     */
    public function getEnumValues(): array;
}