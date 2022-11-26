<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\FindVariant;

use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @package inventory
 */
class FindProductVariantRouteResponse extends StoreApiResponse
{
    /**
     * @var FoundCombination
     */
    protected $object;

    public function __construct(FoundCombination $object)
    {
        parent::__construct($object);
    }

    public function getFoundCombination(): FoundCombination
    {
        return $this->object;
    }
}
