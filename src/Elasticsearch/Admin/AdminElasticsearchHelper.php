<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('system-settings')]
class AdminElasticsearchHelper
{
    public function __construct(
        private bool $adminEsEnabled,
        private readonly bool $refreshIndices,
        private readonly string $adminIndexPrefix
    ) {
    }

    public function getEnabled(): bool
    {
        return $this->adminEsEnabled;
    }

    /**
     * Only used for unit tests because the container parameter bag is frozen and can not be changed at runtime.
     * Therefore this function can be used to test different behaviours
     *
     * @internal
     */
    public function setEnabled(bool $enabled): self
    {
        $this->adminEsEnabled = $enabled;

        return $this;
    }

    public function getRefreshIndices(): bool
    {
        return $this->refreshIndices;
    }

    public function getPrefix(): string
    {
        return $this->adminIndexPrefix;
    }

    public function getIndex(string $name): string
    {
        return $this->adminIndexPrefix . '-' . \strtolower(\str_replace(['_', ' '], '-', $name));
    }
}
