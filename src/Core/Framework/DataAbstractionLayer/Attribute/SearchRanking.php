<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class SearchRanking
{
    final public const ASSOCIATION_SEARCH_RANKING = 0.25;
    final public const MIDDLE_SEARCH_RANKING = 250;
    final public const LOW_SEARCH_RANKING = 80;
    final public const HIGH_SEARCH_RANKING = 500;

    public function __construct(
        public float $ranking,
        public bool $tokenize = true
    ) {
    }
}
