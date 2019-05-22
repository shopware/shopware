<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Aggregate\PromotionTranslation;

use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class PromotionTranslationDefinition extends EntityTranslationDefinition
{
    public function getEntityName(): string
    {
        return 'promotion_translation';
    }

    public function getEntityClass(): string
    {
        return PromotionTranslationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return PromotionTranslationCollection::class;
    }

    public function getParentDefinitionClass(): string
    {
        return PromotionDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
        ]);
    }
}
