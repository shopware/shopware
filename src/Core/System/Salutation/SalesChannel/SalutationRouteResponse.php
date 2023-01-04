<?php declare(strict_types=1);

namespace Shopware\Core\System\Salutation\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Shopware\Core\System\Salutation\SalutationCollection;

#[Package('customer-order')]
class SalutationRouteResponse extends StoreApiResponse
{
    /**
     * @var EntitySearchResult
     */
    protected $object;

    public function __construct(EntitySearchResult $object)
    {
        parent::__construct($object);
    }

    public function getSalutations(): SalutationCollection
    {
        /** @var SalutationCollection $collection */
        $collection = $this->object->getEntities();

        return $collection;
    }
}
