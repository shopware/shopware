<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class PluginNotFoundException extends PluginException
{
    public function __construct(string $pluginName)
    {
        parent::__construct(
            Response::HTTP_NOT_FOUND,
            'FRAMEWORK__PLUGIN_NOT_FOUND',
            'Plugin by name "{{ name }}" not found.',
            ['name' => $pluginName]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__PLUGIN_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
