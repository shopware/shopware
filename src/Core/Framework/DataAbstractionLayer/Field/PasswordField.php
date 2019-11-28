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
     * @var string|int
     */
    private $algorithm;

    /**
     * @var array
     */
    private $hashOptions;

    /**
     * @param string|int $algorithm
     */
    public function __construct(string $storageName, string $propertyName, $algorithm = null, array $hashOptions = [])
    {
        parent::__construct($propertyName);
        $this->storageName = $storageName;
        $defaultAlgorithm = version_compare(PHP_VERSION, '7.4.0') >= 0 ? 'DEFAULT' : PASSWORD_DEFAULT;
        $this->algorithm = $algorithm ?? $defaultAlgorithm;
        $this->hashOptions = $hashOptions;
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    /**
     * since php 7.4 the algorithms are identified as string -> https://wiki.php.net/rfc/password_registry#backward_incompatible_changes
     *
     * @return int|string
     */
    public function getAlgorithm()
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
