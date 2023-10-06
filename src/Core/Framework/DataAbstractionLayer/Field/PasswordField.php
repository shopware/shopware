<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PasswordFieldSerializer;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class PasswordField extends Field implements StorageAware
{
    final public const FOR_CUSTOMER = 'customer';

    final public const FOR_ADMIN = 'admin';

    private readonly string $algorithm;

    /**
     * @param array<int, string> $hashOptions
     */
    public function __construct(
        private readonly string $storageName,
        string $propertyName,
        ?string $algorithm = \PASSWORD_DEFAULT,
        private readonly array $hashOptions = [],
        private readonly ?string $for = null
    ) {
        parent::__construct($propertyName);
        $this->algorithm = $algorithm ?? \PASSWORD_DEFAULT;
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    /**
     * since php 7.4 the algorithms are identified as string -> https://wiki.php.net/rfc/password_registry#backward_incompatible_changes
     */
    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    /**
     * @return array<int, string>
     */
    public function getHashOptions(): array
    {
        return $this->hashOptions;
    }

    public function getFor(): ?string
    {
        return $this->for;
    }

    protected function getSerializerClass(): string
    {
        return PasswordFieldSerializer::class;
    }
}
