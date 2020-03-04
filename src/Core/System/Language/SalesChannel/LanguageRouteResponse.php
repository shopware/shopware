<?php declare(strict_types=1);

namespace Shopware\Core\System\Language\SalesChannel;

use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class LanguageRouteResponse extends StoreApiResponse
{
    /**
     * @var LanguageCollection
     */
    protected $object;

    public function __construct(LanguageCollection $languages)
    {
        parent::__construct($languages);
    }

    public function getLanguages(): LanguageCollection
    {
        return $this->object;
    }
}
