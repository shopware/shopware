<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

class AppNotFoundException extends \Exception
{
    public function __construct(string $appId)
    {
        parent::__construct(sprintf('App for ID: "%s" could not be found.', $appId));
    }
}
