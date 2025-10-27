<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Robots\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Page\Robots\Parser\ParsedRobots;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched after robots.txt content has been parsed.
 *
 * Allows developers to:
 * - Modify the parsed result (add/remove user-agent blocks, directives)
 * - Add custom validation and issues
 * - Transform directives based on custom logic
 *
 * @example
 * ```php
 * #[AsEventListener(event: RobotsDirectiveParsingEvent::class)]
 * class CustomRobotsValidationSubscriber
 * {
 *     public function onParsingComplete(RobotsDirectiveParsingEvent $event): void
 *     {
 *         $result = $event->getParsedResult();
 *
 *         // Add custom validation logic
 *         if ($this->hasInvalidConfiguration($result)) {
 *             $event->addIssue(new ParseIssue(...));
 *         }
 *     }
 * }
 * ```
 */
#[Package('framework')]
class RobotsDirectiveParsingEvent extends Event implements ShopwareEvent
{
    public function __construct(
        private readonly string $text,
        private ParsedRobots $parsedResult,
        private readonly Context $context,
        private readonly ?string $salesChannelId = null
    ) {
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getParsedResult(): ParsedRobots
    {
        return $this->parsedResult;
    }

    public function setParsedResult(ParsedRobots $parsedResult): void
    {
        $this->parsedResult = $parsedResult;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }
}
