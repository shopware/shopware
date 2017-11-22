<?php declare(strict_types=1);

namespace Shopware\Rest;

use Shopware\Context\Struct\ShopContext;
use Shopware\Context\Struct\TranslationContext;
use Symfony\Component\HttpFoundation\Request;

class RestContext
{
    /**
     * @var ShopContext
     */
    private $shopContext;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var string
     */
    private $userId;

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

    public function getTranslationContext(): TranslationContext
    {
        return $this->getShopContext()->getTranslationContext();
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
