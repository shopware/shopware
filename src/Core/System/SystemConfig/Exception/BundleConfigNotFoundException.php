<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Throwable;

class BundleConfigNotFoundException extends ShopwareHttpException
{
    protected $code = 'BUNDLE-CONFIG-NOT-FOUND';

    public function __construct(string $bundleName, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('Could not find "Resources/config.xml" for bundle "%s"', $bundleName);

        parent::__construct($message, $code, $previous);
    }
}
