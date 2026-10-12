<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Garan;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct<array{svg: string|null, nestedSvg: string|null}>>
 */
#[Package('inventory')]
class GaranLabelRouteResponse extends StoreApiResponse
{
    public function __construct(?string $svg, ?string $nestedSvg = null)
    {
        parent::__construct(new ArrayStruct(['svg' => $svg, 'nestedSvg' => $nestedSvg], 'garan_label'));
    }
}
