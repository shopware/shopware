<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

use Shopware\Core\Framework\App\Validation\Error\Error;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[Package('core')]
class InvalidAppConfigurationException extends \RuntimeException
{
    public function __construct(Error $error)
    {
        parent::__construct($error->getMessage());
    }
}
