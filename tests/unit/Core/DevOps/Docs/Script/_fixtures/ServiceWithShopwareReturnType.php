<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Docs\Script\_fixtures;

use Shopware\Core\DevOps\Docs\Script\ServiceReferenceGenerator;

/**
 * @script-service data_loading
 */
class ServiceWithShopwareReturnType
{
    /**
     * @return ServiceReferenceGenerator
     */
    public function foo(): void
    {
    }
}
