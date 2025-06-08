<?php

namespace PHPSTORM_META {
    expectedArguments(
        \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::setTotalCountMode(),
        0,
        \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::TOTAL_COUNT_MODE_NONE,
        \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::TOTAL_COUNT_MODE_EXACT,
        \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::TOTAL_COUNT_MODE_NEXT_PAGES
    );

    expectedArguments(
        \Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting::__construct(),
        1,
        \Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting::ASCENDING,
        \Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting::DESCENDING
    );

}
