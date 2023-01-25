<?php declare(strict_types=1);

namespace Shopware\Core\System\Salutation\Aggregate\SalutationTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Salutation\SalutationDefinition;

#[Package('customer-order')]
class SalutationTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'salutation_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return SalutationTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return SalutationTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return SalutationDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('display_name', 'displayName'))->addFlags(new ApiAware(), new Required()),
            (new StringField('letter_name', 'letterName'))->addFlags(new ApiAware(), new Required()),
            (new CustomFields())->addFlags(new ApiAware()),
        ]);
    }
}
