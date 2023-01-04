<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Awareness\HookServiceFactory;

#[Package('core')]
class NoHookServiceFactoryException extends \RuntimeException
{
    public function __construct(string $service)
    {
        parent::__construct(sprintf('Service "%s" must extend the abstract "%s" so that this service may also be used in scripts.', $service, HookServiceFactory::class));
    }
}
