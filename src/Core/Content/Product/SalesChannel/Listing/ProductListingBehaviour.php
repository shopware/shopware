<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Shopware\Core\Framework\Struct\Struct;

class ProductListingBehaviour extends Struct
{
    public const EXTENSION_NAME = 'behaviour';

    protected $preferMainVariant = true;

    public function isPreferMainVariant(): bool
    {
        return $this->preferMainVariant;
    }

    public function setPreferMainVariant(bool $preferMainVariant): self
    {
        $this->preferMainVariant = $preferMainVariant;

        return $this;
    }
}
