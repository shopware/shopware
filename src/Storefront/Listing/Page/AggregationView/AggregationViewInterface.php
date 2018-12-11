<?php declare(strict_types=1);

namespace Shopware\Storefront\Listing\Page\AggregationView;

interface AggregationViewInterface
{
    public function getAggregationName(): string;

    public function isActive(): bool;

    public function getLabel(): string;

    public function getType(): string;
}
