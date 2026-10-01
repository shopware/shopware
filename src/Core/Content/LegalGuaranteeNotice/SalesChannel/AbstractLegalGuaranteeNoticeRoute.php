<?php declare(strict_types=1);

namespace Shopware\Core\Content\LegalGuaranteeNotice\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
abstract class AbstractLegalGuaranteeNoticeRoute
{
    abstract public function getDecorated(): AbstractLegalGuaranteeNoticeRoute;

    abstract public function load(SalesChannelContext $context): LegalGuaranteeNoticeRouteResponse;
}
