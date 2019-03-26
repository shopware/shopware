<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class PluginNotAZipFileException extends ShopwareHttpException
{
    protected $code = 'PLUGIN-NOT-A-ZIP-FILE';

    public function __construct(string $reason, int $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('Given file must be a zip file. Given: %s', $reason);

        parent::__construct($message, $code, $previous);
    }
}
