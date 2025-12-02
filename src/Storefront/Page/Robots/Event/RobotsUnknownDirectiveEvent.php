<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Robots\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Page\Robots\Parser\ParseIssue;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched when an unknown directive is encountered during robots.txt parsing.
 *
 * Allows developers to:
 * - Handle custom directives not in the standard set
 * - Prevent warnings for known-custom directives
 * - Set custom issues for specific directive types
 *
 * @codeCoverageIgnore Simple DTO with no business logic
 */
#[Package('framework')]
class RobotsUnknownDirectiveEvent extends Event implements ShopwareEvent
{
    /**
     * Mark as true to prevent this directive from being logged as a warning.
     */
    public bool $handled = false;

    /**
     * Set a custom issue for this directive. If set, this issue will be used instead of the default warning.
     */
    public ?ParseIssue $issue = null;

    public function __construct(
        public readonly int $lineNumber,
        public readonly string $line,
        public readonly string $directiveType,
        public readonly string $directiveValue,
        public readonly bool $insideUserAgentBlock,
        public readonly Context $context,
        public readonly ?string $salesChannelId = null
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
