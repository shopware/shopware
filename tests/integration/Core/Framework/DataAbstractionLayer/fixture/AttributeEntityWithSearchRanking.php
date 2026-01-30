<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture;

use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopware\Core\System\Currency\CurrencyEntity;

/**
 * Test entity for verifying #[SearchRanking] attribute functionality.
 *
 * @internal
 */
#[Entity('attribute_entity_search_ranking', since: '6.7.0.0')]
class AttributeEntityWithSearchRanking extends EntityStruct
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID)]
    public string $id;

    #[Field(type: FieldType::STRING)]
    public string $name;

    #[ForeignKey(entity: 'currency')]
    public ?string $currencyId = null;

    #[SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING, true)]
    #[ManyToOne(entity: 'currency')]
    public ?CurrencyEntity $currency = null;

    #[SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING, false)]
    #[Field(type: FieldType::STRING)]
    public ?string $middleRankedString = null;

    #[SearchRanking(SearchRanking::LOW_SEARCH_RANKING, true)]
    #[Field(type: FieldType::STRING)]
    public ?string $lowRankedString = null;

    #[SearchRanking(SearchRanking::HIGH_SEARCH_RANKING, false)]
    #[Field(type: FieldType::STRING)]
    public ?string $highRankedString = null;
}
