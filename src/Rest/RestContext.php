<?php declare(strict_types=1);

namespace Shopware\Rest;

use Shopware\Context\Struct\StorefrontContext;
use Shopware\Context\Struct\TranslationContext;
use Symfony\Component\HttpFoundation\Request;

class RestContext
{
    /**
     * @var StorefrontContext
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
        StorefrontContext $shopContext,
        ?string $userId
    ) {
        $this->request = $request;
        $this->shopContext = $shopContext;
        $this->userId = $userId;
    }

    public function getShopContext(): StorefrontContext
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
