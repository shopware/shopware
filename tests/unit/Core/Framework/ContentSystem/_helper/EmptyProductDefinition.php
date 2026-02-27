<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\_helper;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
final class EmptyProductDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'product';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([]);
    }
}
