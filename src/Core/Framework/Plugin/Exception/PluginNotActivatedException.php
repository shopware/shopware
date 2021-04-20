<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class PluginNotActivatedException extends ShopwareHttpException
{
    public function __construct(string $pluginName, ?\Throwable $previous = null)
    {
        parent::__construct(
            'Plugin "{{ plugin }}" is not activated.',
            ['plugin' => $pluginName],
            $previous
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__PLUGIN_NOT_ACTIVATED';
    }
}
