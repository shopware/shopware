<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

class InvalidAppConfigurationException extends \RuntimeException
{
    public function __construct(string $invalidElement)
    {
        parent::__construct(sprintf('Custom component "%s" is not allowed to be used in app configuration.', $invalidElement));
    }
}
