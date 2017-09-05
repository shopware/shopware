<?php declare(strict_types=1);


namespace Shopware\Api;

use Shopware\Context\Struct\ShopContext;

class ApiContext
{
    /**
     * @var mixed
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
    private $resultFormat = ResultFormat::BASIC;

    /**
     * @var array
     */
    private $parameters;

    public function __construct($payload, ShopContext $shopContext, array $parameters = [], string $outputFormat, string $resultFormat)
    {
        $this->outputFormat = $outputFormat;
        $this->payload = $payload;
        $this->shopContext = $shopContext;
        $this->parameters = $parameters;
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

    public function getResultFormat(): string
    {
        return $this->resultFormat;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }
}
