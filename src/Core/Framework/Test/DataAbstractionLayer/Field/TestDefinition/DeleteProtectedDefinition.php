<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class DeleteProtectedDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return '_test_nullable';
    }

    public static function getDeleteProtectionKey(): ?string
    {
        return 'add_me';
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection();
    }
}
