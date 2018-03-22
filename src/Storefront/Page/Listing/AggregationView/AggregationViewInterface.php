<?php

namespace Shopware\Storefront\Page\Listing\AggregationView;

interface AggregationViewInterface
{
    public function getAggregationName(): string;

    public function isActive(): bool;

    public function getLabel(): string;

    public function getType(): string;
}