<?php declare(strict_types=1);

namespace Shopware\Core\System\Integration;

use Shopware\Core\Framework\ORM\EntityCollection;

class IntegrationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return IntegrationStruct::class;
    }
}
