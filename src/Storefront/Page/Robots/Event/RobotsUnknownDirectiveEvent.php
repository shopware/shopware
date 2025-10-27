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
 * - Add custom directives to the current parsing context
 *
 * @example
 * ```php
 * #[AsEventListener(event: RobotsUnknownDirectiveEvent::class)]
 * class YandexDirectiveSubscriber
 * {
 *     public function onUnknownDirective(RobotsUnknownDirectiveEvent $event): void
 *     {
 *         if ($event->getDirectiveType() === 'Clean-param') {
 *             // Handle Yandex-specific directive
 *             $event->setHandled(true); // Don't log as warning
 *         }
 *     }
 * }
 * ```
 */
#[Package('framework')]
class RobotsUnknownDirectiveEvent extends Event implements ShopwareEvent
{
    private bool $handled = false;

    private ?ParseIssue $issue = null;

    public function __construct(
        private readonly int $lineNumber,
        private readonly string $line,
        private readonly string $directiveType,
        private readonly string $directiveValue,
        private readonly bool $insideUserAgentBlock,
        private readonly Context $context,
        private readonly ?string $salesChannelId = null
    ) {
    }

    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

    public function getLine(): string
    {
        return $this->line;
    }

    public function getDirectiveType(): string
    {
        return $this->directiveType;
    }

    public function getDirectiveValue(): string
    {
        return $this->directiveValue;
    }

    public function isInsideUserAgentBlock(): bool
    {
        return $this->insideUserAgentBlock;
    }

    /**
     * Mark this directive as handled to prevent it from being logged as a warning.
     */
    public function setHandled(bool $handled): void
    {
        $this->handled = $handled;
    }

    public function isHandled(): bool
    {
        return $this->handled;
    }

    /**
     * Set a custom issue for this directive.
     * If set, this issue will be used instead of the default warning.
     */
    public function setIssue(?ParseIssue $issue): void
    {
        $this->issue = $issue;
    }

    public function getIssue(): ?ParseIssue
    {
        return $this->issue;
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
