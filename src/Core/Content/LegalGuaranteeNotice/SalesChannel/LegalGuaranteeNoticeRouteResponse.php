<?php declare(strict_types=1);

namespace Shopware\Core\Content\LegalGuaranteeNotice\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct<array{svg: string|null, link: string|null}>>
 */
#[Package('inventory')]
class LegalGuaranteeNoticeRouteResponse extends StoreApiResponse
{
    public function __construct(?string $svg, ?string $link)
    {
        parent::__construct(new ArrayStruct(['svg' => $svg, 'link' => $link], 'legal_guarantee_notice'));
    }
}
