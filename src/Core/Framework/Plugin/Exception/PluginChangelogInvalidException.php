<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

/**
 * @deprecated tag:v6.6.0 - will be removed without a replacement
 */
#[Package('core')]
class PluginChangelogInvalidException extends ShopwareHttpException
{
    public function __construct(string $changelogPath)
    {
        Feature::triggerDeprecationOrThrow('v6.6.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, '6.6.0'));

        parent::__construct(
            'The changelog of "{{ changelogPath }}" is invalid.',
            ['changelogPath' => $changelogPath]
        );
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow('v6.6.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, '6.6.0'));

        return 'FRAMEWORK__PLUGIN_CHANGELOG_INVALID';
    }
}
