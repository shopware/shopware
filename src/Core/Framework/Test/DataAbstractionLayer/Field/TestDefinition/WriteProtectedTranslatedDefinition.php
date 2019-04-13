<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class WriteProtectedTranslatedDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return '_test_nullable';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new TranslatedField('protected'))->addFlags(new WriteProtected()),
            (new TranslatedField('systemProtected'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            new TranslationsAssociationField(WriteProtectedTranslationDefinition::class, 'wp_id'),
        ]);
    }

    protected function defaultFields(): array
    {
        return [];
    }
}
