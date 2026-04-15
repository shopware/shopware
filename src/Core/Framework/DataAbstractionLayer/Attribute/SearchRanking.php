<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking as SearchRankingFlag;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class SearchRanking
{
    final public const ASSOCIATION_SEARCH_RANKING = SearchRankingFlag::ASSOCIATION_SEARCH_RANKING;
    final public const MIDDLE_SEARCH_RANKING = SearchRankingFlag::MIDDLE_SEARCH_RANKING;
    final public const LOW_SEARCH_RANKING = SearchRankingFlag::LOW_SEARCH_RANKING;
    final public const HIGH_SEARCH_RANKING = SearchRankingFlag::HIGH_SEARCH_RANKING;

    public function __construct(
        public float $ranking,
        public bool $tokenize = true
    ) {
    }
}
