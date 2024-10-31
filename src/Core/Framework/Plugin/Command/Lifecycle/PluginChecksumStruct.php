<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Lifecycle;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('core')]
class PluginChecksumStruct extends Struct
{
    protected string $algorithm;

    /**
     * @var array<string>
     */
    protected array $fileExtensions;

    /**
     * @var array<string>
     */
    protected array $hashes;

    protected string $pluginVersion;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return (new self())->assign($data);
    }

    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    /**
     * @return array<string>
     */
    public function getFileExtensions(): array
    {
        return $this->fileExtensions;
    }

    /**
     * @return array<string, string>
     */
    public function getHashes(): array
    {
        return $this->hashes;
    }

    public function getPluginVersion(): string
    {
        return $this->pluginVersion;
    }
}
