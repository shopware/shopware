<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PasswordFieldSerializer;

/**
 * @package core
 */
class PasswordField extends Field implements StorageAware
{
    public const FOR_CUSTOMER = 'customer';

    public const FOR_ADMIN = 'admin';

    private string $storageName;

    private string $algorithm;

    /**
     * @var array<int, string>
     */
    private array $hashOptions;

    private ?string $for;

    /**
     * @param array<int, string> $hashOptions
     */
    public function __construct(
        string $storageName,
        string $propertyName,
        ?string $algorithm = \PASSWORD_DEFAULT,
        array $hashOptions = [],
        ?string $for = null
    ) {
        parent::__construct($propertyName);
        $this->storageName = $storageName;
        $this->algorithm = $algorithm ?? \PASSWORD_DEFAULT;
        $this->hashOptions = $hashOptions;
        $this->for = $for;
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
