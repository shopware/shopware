<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PasswordFieldSerializer;

class PasswordField extends Field implements StorageAware
{
    /**
     * @var string
     */
    private $storageName;

    /**
     * @var string
     */
    private $algorithm;

    /**
     * @var array
     */
    private $hashOptions;

    public function __construct(string $storageName, string $propertyName, ?string $algorithm = \PASSWORD_DEFAULT, array $hashOptions = [])
    {
        parent::__construct($propertyName);
        $this->storageName = $storageName;
        $this->algorithm = $algorithm ?? \PASSWORD_DEFAULT;
        $this->hashOptions = $hashOptions;
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

    public function getHashOptions(): array
    {
        return $this->hashOptions;
    }

    protected function getSerializerClass(): string
    {
        return PasswordFieldSerializer::class;
    }
}
