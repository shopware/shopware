<?php declare(strict_types=1);

namespace Shopware\Rest;

use Shopware\Context\Struct\ShopContext;

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

    public function __construct(array $payload, ShopContext $shopContext, array $parameters = [], string $outputFormat, string $resultFormat = ResultFormat::BASIC)
    {
        $this->outputFormat = $outputFormat;
        $this->payload = $payload;
        $this->shopContext = $shopContext;
        $this->parameters = $parameters;
        $this->resultFormat = $resultFormat;
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
