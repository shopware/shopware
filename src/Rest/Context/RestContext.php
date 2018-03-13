<?php declare(strict_types=1);

namespace Shopware\Rest\Context;

use Shopware\Context\Struct\ShopContext;
use Symfony\Component\HttpFoundation\Request;

class RestContext
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var string
     */
    private $userId;

    /**
     * @var ShopContext
     */
    private $shopContext;

    public function __construct(
        Request $request,
        ShopContext $shopContext,
        ?string $userId
    ) {
        $this->request = $request;
        $this->shopContext = $shopContext;
        $this->userId = $userId;
    }

    public function getShopContext(): ShopContext
    {
        return $this->shopContext;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
