<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Aggregate\NumberRangeTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\NumberRange\NumberRangeDefinition;

#[Package('checkout')]
class NumberRangeTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'number_range_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return NumberRangeTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return NumberRangeTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return NumberRangeDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required()),
            new StringField('description', 'description'),
            new CustomFields(),
        ]);
    }
}
