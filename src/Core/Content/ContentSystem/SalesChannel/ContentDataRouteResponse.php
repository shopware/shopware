<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel;

use Shopware\Core\Content\ContentSystem\Output\Struct\ContentDataPage;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('discovery')]
class ContentDataRouteResponse extends AbstractContentRouteResponse
{
    private readonly ContentDataPage $dataPage;

    public function __construct(
        ContentDataPage $dataPage,
    ) {
        parent::__construct($dataPage);
        $this->dataPage = $dataPage;
    }

    public function getContentDataPage(): ContentDataPage
    {
        return $this->dataPage;
    }
}
