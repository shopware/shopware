<?php declare(strict_types=1);

namespace Shopware\Rest;

use Shopware\Context\Struct\ShopContext;
use Shopware\Context\Struct\TranslationContext;

class ApiContext
{
    /**
     * @var array
     */
    private $payload;

    /**
     * @var string
     */
    private $outputFormat;

    /**
     * @var ShopContext
     */
    private $shopContext;

    /**
     * @var string
     */
    private $resultFormat;

    /**
     * @var array
     */
    private $parameters;

    /**
     * @var string
     */
    private $userId;

    public function __construct(
        array $payload,
        ShopContext $shopContext,
        ?string $userId = null,
        array $parameters = [],
        string $outputFormat,
        string $resultFormat = ResultFormat::BASIC
    ) {
        $this->outputFormat = $outputFormat;
        $this->payload = $payload;
        $this->shopContext = $shopContext;
        $this->parameters = $parameters;
        $this->resultFormat = $resultFormat;
        $this->userId = $userId;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getOutputFormat(): string
    {
        return $this->outputFormat;
    }

    public function getShopContext(): ShopContext
    {
        return $this->shopContext;
    }

    public function getTranslationContext(): TranslationContext
    {
        return $this->getShopContext()->getTranslationContext();
    }

    public function getResultFormat(): string
    {
        return $this->resultFormat;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }
}
