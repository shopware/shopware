<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class NoPluginFoundInZipException extends ShopwareHttpException
{
    protected $code = 'NO-PLUGIN-FOUND-IN-ZIP';

    public function __construct(string $archive, int $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('No plugin was found in the zip archive %s', $archive);

        parent::__construct($message, $code, $previous);
    }
}
