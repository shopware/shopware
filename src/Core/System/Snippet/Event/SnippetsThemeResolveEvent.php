<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Event;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class SnippetsThemeResolveEvent extends Event
{
    /**
     * @var list<string>
     */
    private array $usedThemes = [];

    /**
     * @var list<string>
     */
    private array $unusedThemes = [];

    public function __construct(
        private readonly ?string $salesChannelId = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getUsedThemes(): array
    {
        return $this->usedThemes;
    }

    /**
     * @param list<string> $usedThemes
     */
    public function setUsedThemes(array $usedThemes): void
    {
        $this->usedThemes = $usedThemes;
    }

    /**
     * @return list<string>
     */
    public function getUnusedThemes(): array
    {
        return $this->unusedThemes;
    }

    /**
     * @param list<string> $unusedThemes
     */
    public function setUnusedThemes(array $unusedThemes): void
    {
        $this->unusedThemes = $unusedThemes;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }
}
