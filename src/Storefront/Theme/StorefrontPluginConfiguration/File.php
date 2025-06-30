<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\StorefrontPluginConfiguration;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('framework')]
class File extends Struct
{
    protected string $bundleName = '';

    /**
     * @param array<string, string> $resolveMapping
     */
    public function __construct(
        protected string $filepath,
        protected array $resolveMapping = [],
        public ?string $assetName = null
    ) {
    }

    public function getFilepath(): string
    {
        return $this->filepath;
    }

    public function setFilepath(string $filepath): void
    {
        $this->filepath = $filepath;
    }

    /**
     * @return array<string, string>
     */
    public function getResolveMapping(): array
    {
        return $this->resolveMapping;
    }

    /**
     * @param array<string, string> $resolveMapping
     */
    public function setResolveMapping(array $resolveMapping): void
    {
        $this->resolveMapping = $resolveMapping;
    }

    public function getBundleName(): string
    {
        return $this->bundleName;
    }

    public function setBundleName(string $bundleName): void
    {
        $this->bundleName = $bundleName;
    }
}
