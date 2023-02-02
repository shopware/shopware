<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage\SalesChannel;

use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class LandingPageRouteResponse extends StoreApiResponse
{
    /**
     * @var LandingPageEntity
     */
    protected $object;

    public function __construct(LandingPageEntity $landingPage)
    {
        parent::__construct($landingPage);
    }

    public function getLandingPage(): LandingPageEntity
    {
        return $this->object;
    }
}
