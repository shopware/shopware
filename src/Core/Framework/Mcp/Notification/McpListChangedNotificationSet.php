<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Notification;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 */
#[Package('framework')]
final readonly class McpListChangedNotificationSet
{
    public function __construct(
        public bool $tools,
        public bool $resources,
        public bool $prompts,
    ) {
    }

    public static function none(): self
    {
        return new self(false, false, false);
    }

    public function merge(self $other): self
    {
        return new self(
            tools: $this->tools || $other->tools,
            resources: $this->resources || $other->resources,
            prompts: $this->prompts || $other->prompts,
        );
    }

    public function hasChanges(): bool
    {
        return $this->tools || $this->resources || $this->prompts;
    }
}
