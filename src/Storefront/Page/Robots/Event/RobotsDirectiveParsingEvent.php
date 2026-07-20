<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Robots\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Page\Robots\Parser\ParsedRobots;

/**
 * Event dispatched after robots.txt content has been parsed.
 *
 * Allows developers to:
 * - Modify the parsed result (add/remove user-agent blocks, directives)
 * - Add custom validation and issues
 * - Transform directives based on custom logic
 */
#[Package('framework')]
class RobotsDirectiveParsingEvent implements ShopwareEvent
{
    public function __construct(
        public readonly string $text,
        public ParsedRobots $parsedResult,
        public readonly Context $context,
        public readonly ?string $salesChannelId = null
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
