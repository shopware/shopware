<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

use Shopware\Core\Framework\App\Validation\Error\Error;

class InvalidAppConfigurationException extends \RuntimeException
{
    public function __construct(Error $error)
    {
        parent::__construct($error->getMessage());
    }
}
