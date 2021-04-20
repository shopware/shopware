<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class PluginNotAZipFileException extends ShopwareHttpException
{
    public function __construct(string $mimeType, ?\Throwable $previous = null)
    {
        parent::__construct(
            'Given file must be a zip file. Given: {{ mimeType }}',
            ['mimeType' => $mimeType],
            $previous
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__PLUGIN_NOT_A_ZIP_FILE';
    }
}
