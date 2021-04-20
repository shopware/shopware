<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class PluginNotInstalledException extends ShopwareHttpException
{
    public function __construct(string $pluginName, ?\Throwable $previous = null)
    {
        parent::__construct(
            'Plugin "{{ plugin }}" is not installed.',
            ['plugin' => $pluginName],
            $previous
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__PLUGIN_NOT_INSTALLED';
    }
}
