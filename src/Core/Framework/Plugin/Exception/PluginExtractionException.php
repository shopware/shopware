<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class PluginExtractionException extends ShopwareHttpException
{
    protected $code = 'PLUGIN-EXTRACTION';

    public function __construct(string $reason, int $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('Plugin extraction failed. Error: %s', $reason);

        parent::__construct($message, $code, $previous);
    }
}
