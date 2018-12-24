<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Throwable;

class PluginToPluginRequirementException extends ShopwareHttpException
{
    protected $code = 'PLUGIN-REQUIREMENT-MISMATCH';

    public function __construct(string $message, int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
