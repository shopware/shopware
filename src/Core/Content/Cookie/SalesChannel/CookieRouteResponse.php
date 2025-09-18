<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\SalesChannel;

use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @codeCoverageIgnore
 *
 * @extends StoreApiResponse<ArrayStruct<array{elements: CookieGroupCollection, hash: string}>>
 */
#[Package('framework')]
class CookieRouteResponse extends StoreApiResponse
{
    public function __construct(
        CookieGroupCollection $cookieGroups,
        string $hash,
    ) {
        parent::__construct(new ArrayStruct([
            'elements' => $cookieGroups,
            'hash' => $hash,
        ], 'cookie_groups_hash'));
    }

    public function getCookieGroups(): CookieGroupCollection
    {
        return $this->object->get('elements');
    }

    public function getHash(): string
    {
        return $this->object->get('hash');
    }
}
