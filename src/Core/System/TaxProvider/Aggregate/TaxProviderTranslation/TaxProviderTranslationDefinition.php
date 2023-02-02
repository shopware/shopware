<?php declare(strict_types=1);

namespace Shopware\Core\System\TaxProvider\Aggregate\TaxProviderTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\TaxProvider\TaxProviderDefinition;

#[Package('checkout')]
class TaxProviderTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'tax_provider_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return TaxProviderTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return TaxProviderTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.5.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return TaxProviderDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required()),
            (new CustomFields())->addFlags(new ApiAware()),
        ]);
    }
}
