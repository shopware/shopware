<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Garan;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

#[Package('inventory')]
class GaranLabelRouteResponse extends StoreApiResponse
{
    /**
     * @var ArrayStruct
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $object;

    public function __construct(?string $svg, ?string $nestedSvg = null)
    {
        parent::__construct(new ArrayStruct(['svg' => $svg, 'nestedSvg' => $nestedSvg], 'garan_label'));
    }

    public function getObject(): ArrayStruct
    {
        return $this->object;
    }
}
