<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ORM\Field\TestDefinition;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\FieldCollection;

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

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection();
    }
}
