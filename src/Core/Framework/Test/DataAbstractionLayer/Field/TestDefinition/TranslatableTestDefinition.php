<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class TranslatableTestDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'translatable_test';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getHydratorClass(): string
    {
        return TranslatableTestHydrator::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey()),

            (new TranslatedField('name'))->addFlags(new ApiAware()),

            (new TranslationsAssociationField(TranslatableTestTranslationDefinition::class, 'translatable_test_id'))->addFlags(new Required()),
        ]);
    }
}
