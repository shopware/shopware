<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Script\Facade;

use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;

/**
 * @internal
 */
#[Package('core')]
class CacheInvalidatorFacadeHookFactory extends HookServiceFactory
{
    public function __construct(private readonly CacheInvalidator $cacheInvalidator)
    {
    }

    public function factory(Hook $hook, Script $script): CacheInvalidatorFacade
    {
        return new CacheInvalidatorFacade($this->cacheInvalidator);
    }

    public function getName(): string
    {
        return 'cache';
    }
}
