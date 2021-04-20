<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class NoPluginFoundInZipException extends ShopwareHttpException
{
    public function __construct(string $archive, ?\Throwable $previous = null)
    {
        parent::__construct(
            'No plugin was found in the zip archive: {{ archive }}',
            ['archive' => $archive],
            $previous
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__PLUGIN_NO_PLUGIN_FOUND_IN_ZIP';
    }
}
