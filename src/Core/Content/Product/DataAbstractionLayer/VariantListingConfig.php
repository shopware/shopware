<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Shopware\Core\Framework\Struct\Struct;

class VariantListingConfig extends Struct
{
    protected ?bool $displayParent;

    protected ?string $mainVariantId;

    /**
     * @var array<string, string>|null
     */
    protected ?array $configuratorGroupConfig;

    /**
     * @param array<string, string>|null $configuratorGroupConfig
     */
    public function __construct(?bool $displayParent, ?string $mainVariantId, ?array $configuratorGroupConfig)
    {
        $this->displayParent = $displayParent;
        $this->mainVariantId = $mainVariantId;
        $this->configuratorGroupConfig = $configuratorGroupConfig;
    }

    public function getDisplayParent(): ?bool
    {
        return $this->displayParent;
    }

    public function getMainVariantId(): ?string
    {
        return $this->mainVariantId;
    }

    /**
     * @return array<string, string>|null
     */
    public function getConfiguratorGroupConfig(): ?array
    {
        return $this->configuratorGroupConfig;
    }
}
