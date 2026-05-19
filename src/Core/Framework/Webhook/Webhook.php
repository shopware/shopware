<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook;

use Shopware\Core\Framework\Log\Package;

/**
 * Simple DTO for internal use
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class Webhook
{
    public function __construct(
        public string $id,
        public string $webhookName,
        public string $eventName,
        public string $url,
        public bool $onlyLiveVersion,
        public ?string $appId,
        public ?string $appName,
        public ?string $appSourceType,
        public bool $appActive,
        public ?string $appVersion,
        public ?string $appSecret,
        public ?string $appAclRoleId,
    ) {
    }
}
