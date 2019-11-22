<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Aggregate\TaxRule;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Tax\Aggregate\TaxRuleType\TaxRuleTypeDefinition;
use Shopware\Core\System\Tax\TaxDefinition;

class TaxRuleDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'tax_rule';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return TaxRuleCollection::class;
    }

    public function getEntityClass(): string
    {
        return TaxRuleEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('tax_rule_type_id', 'taxRuleTypeId', TaxRuleTypeDefinition::class))->addFlags(new Required()),
            (new FkField('country_id', 'countryId', CountryDefinition::class))->addFlags(new Required()),
            (new FloatField('tax_rate', 'taxRate'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new JsonField('data', 'data', [
                new ListField('states', 'states'),
                new StringField('zipCode', 'zipCode'),
                new StringField('fromZipCode', 'fromZipCode'),
                new StringField('toZipCode', 'toZipCode'),
            ]),
            (new FkField('tax_id', 'taxId', TaxDefinition::class))->addFlags(new Required()),
            (new ManyToOneAssociationField('type', 'tax_rule_type_id', TaxRuleTypeDefinition::class, 'id', true)),
            (new ManyToOneAssociationField('country', 'country_id', CountryDefinition::class, 'id')),
            new ManyToOneAssociationField('tax', 'tax_id', TaxDefinition::class, 'id'),
        ]);
    }
}
