<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
interface FieldEnumProviderInterface
{
    public function isSupported(string $entity, string $fieldName): bool;

    /**
     * @return list<string|bool|int|float>
     */
    public function getChoices(): array;
}
