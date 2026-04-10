<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Docs\Script\_fixtures;

/**
 * @script-service data_loading
 */
class ServiceWithShopwareReturnType
{
    /**
     * @return \Shopware\Core\DevOps\Docs\Script\ServiceReferenceGenerator
     */
    public function foo(): void
    {
    }
}
