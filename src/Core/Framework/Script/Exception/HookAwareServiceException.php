<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Exception;

class HookAwareServiceException extends \RuntimeException
{
    public function __construct(string $service)
    {
        parent::__construct(sprintf('Service %s must implement the interface HookAwareService so that this service may also be used in scripts.', $service));
    }
}
