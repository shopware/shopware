<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

use Shopware\Core\Framework\App\Validation\Error\Error;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class InvalidAppConfigurationException extends \RuntimeException
{
    public function __construct(Error $error)
    {
        parent::__construct($error->getMessage());
    }
}
