<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Robots\Parser;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Page\Robots\Struct\RobotsDirective;
use Shopware\Storefront\Page\Robots\Struct\RobotsUserAgentBlock;

#[Package('framework')]
class ParsedRobots
{
    /**
     * @param list<RobotsUserAgentBlock> $userAgentBlocks
     * @param list<RobotsDirective> $orphanedPathDirectives
     */
    public function __construct(
        public readonly array $userAgentBlocks,
        public readonly array $orphanedPathDirectives
    ) {
    }

    public function hasUserAgentBlocks(): bool
    {
        return \count($this->userAgentBlocks) > 0;
    }
}
