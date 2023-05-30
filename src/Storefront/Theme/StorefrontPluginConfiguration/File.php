<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\StorefrontPluginConfiguration;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('storefront')]
class File extends Struct
{
    /**
     * @var string
     */
    protected $filepath;

    /**
     * @var array
     */
    protected $resolveMapping;

    public function __construct(
        string $filepath,
        array $resolveMapping = []
    ) {
        $this->filepath = $filepath;
        $this->resolveMapping = $resolveMapping;
    }

    public function getFilepath(): string
    {
        return $this->filepath;
    }

    public function setFilepath(string $filepath): void
    {
        $this->filepath = $filepath;
    }

    public function getResolveMapping(): array
    {
        return $this->resolveMapping;
    }

    public function setResolveMapping(array $resolveMapping): void
    {
        $this->resolveMapping = $resolveMapping;
    }
}
