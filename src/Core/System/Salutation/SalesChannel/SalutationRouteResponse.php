<?php declare(strict_types=1);

namespace Shopware\Core\System\Salutation\SalesChannel;

use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Shopware\Core\System\Salutation\SalutationCollection;

class SalutationRouteResponse extends StoreApiResponse
{
    /**
     * @var SalutationCollection
     */
    protected $object;

    public function getSalutations(): SalutationCollection
    {
        return $this->object;
    }
}
