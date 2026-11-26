<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

/**
 * @deprecated tag:v6.8.0 - Will be removed, use StoreException::pluginNotAZipFile instead
 */
#[Package('framework')]
class PluginNotAZipFileException extends ShopwareHttpException
{
    public function __construct(string $mimeType)
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        parent::__construct(
            'Given file must be a zip file. Given: {{ mimeType }}',
            ['mimeType' => $mimeType]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__PLUGIN_NOT_A_ZIP_FILE';
    }
}
