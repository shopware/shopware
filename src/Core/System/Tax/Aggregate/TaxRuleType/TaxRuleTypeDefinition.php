<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Aggregate\TaxRuleType;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Tax\Aggregate\TaxRule\TaxRuleDefinition;
use Shopware\Core\System\Tax\Aggregate\TaxRuleTypeTranslation\TaxRuleTypeTranslationDefinition;

#[Package('customer-order')]
class TaxRuleTypeDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'tax_rule_type';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return TaxRuleTypeCollection::class;
    }

    public function getEntityClass(): string
    {
        return TaxRuleTypeEntity::class;
    }

    public function since(): ?string
    {
        return '6.1.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('technical_name', 'technicalName'))->addFlags(new Required(), new WriteProtected()),
            (new IntField('position', 'position'))->addFlags(new Required()),
            (new TranslatedField('typeName'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new OneToManyAssociationField('rules', TaxRuleDefinition::class, 'tax_rule_type_id'))->addFlags(new RestrictDelete()),
            (new TranslationsAssociationField(TaxRuleTypeTranslationDefinition::class, 'tax_rule_type_id'))->addFlags(new Required()),
        ]);
    }
}
