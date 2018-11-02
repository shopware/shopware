<?php declare(strict_types=1);

namespace Shopware\Core\System\Integration;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class IntegrationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return IntegrationStruct::class;
    }
}
