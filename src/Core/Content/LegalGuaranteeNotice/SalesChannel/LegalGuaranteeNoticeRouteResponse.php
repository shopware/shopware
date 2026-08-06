<?php declare(strict_types=1);

namespace Shopware\Core\Content\LegalGuaranteeNotice\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

#[Package('inventory')]
class LegalGuaranteeNoticeRouteResponse extends StoreApiResponse
{
    /**
     * @var ArrayStruct
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $object;

    public function __construct(?string $svg, ?string $link)
    {
        parent::__construct(new ArrayStruct(['svg' => $svg, 'link' => $link], 'legal_guarantee_notice'));
    }

    public function getObject(): ArrayStruct
    {
        return $this->object;
    }
}
