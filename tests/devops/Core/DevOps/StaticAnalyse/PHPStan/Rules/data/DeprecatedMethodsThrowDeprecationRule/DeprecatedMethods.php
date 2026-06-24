<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\MyFakeNamespace;

use Shopware\Core\Framework\Feature;

class DeprecatedMethods
{
    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     */
    public function deprecatedWithoutTrigger(): void
    {
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     */
    public function deprecatedWithTrigger(): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );
    }

    /**
     * @deprecated tag:v6.8.0 - reason:remove-subscriber - Subscribers are still called for BC
     */
    public function deprecatedWithExceptedReason(): void
    {
    }

    public function notDeprecated(): void
    {
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     */
    private function deprecatedPrivateMethodIsIgnored(): void
    {
    }
}
