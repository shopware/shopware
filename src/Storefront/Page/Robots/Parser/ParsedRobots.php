<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Robots\Parser;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Page\Robots\Struct\RobotsDirective;
use Shopware\Storefront\Page\Robots\Struct\RobotsUserAgentBlock;

#[Package('framework')]
class ParsedRobots
{
    /**
     * @param RobotsUserAgentBlock[] $userAgentBlocks
     * @param RobotsDirective[] $orphanedPathDirectives
     */
    public function __construct(
        private readonly array $userAgentBlocks,
        private readonly array $orphanedPathDirectives
    ) {
    }

    /**
     * @return RobotsUserAgentBlock[]
     */
    public function getUserAgentBlocks(): array
    {
        return $this->userAgentBlocks;
    }

    /**
     * @return RobotsDirective[]
     */
    public function getOrphanedPathDirectives(): array
    {
        return $this->orphanedPathDirectives;
    }

    public function hasUserAgentBlocks(): bool
    {
        return \count($this->userAgentBlocks) > 0;
    }
}
