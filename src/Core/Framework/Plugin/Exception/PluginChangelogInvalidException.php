<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('core')]
class PluginChangelogInvalidException extends ShopwareHttpException
{
    public function __construct(string $changelogPath)
    {
        parent::__construct(
            'The changelog of "{{ changelogPath }}" is invalid.',
            ['changelogPath' => $changelogPath]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__PLUGIN_CHANGELOG_INVALID';
    }
}
