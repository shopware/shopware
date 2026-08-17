<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Webhook;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Validation\WebhookTargetValidator;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class StaticWebhookTargetValidatorFactory
{
    /**
     * @param list<string> $allowedIpAddresses
     */
    public static function create(bool $allowUnencryptedTraffic, array $allowedIpAddresses, bool $allowPublicIpLiterals = false): WebhookTargetValidator
    {
        return new WebhookTargetValidator($allowUnencryptedTraffic, $allowedIpAddresses, static fn (string $host): array => [['ip' => '93.184.216.34']], $allowPublicIpLiterals);
    }
}
