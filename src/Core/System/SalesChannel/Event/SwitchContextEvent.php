<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('core')]
class SwitchContextEvent implements ShopwareSalesChannelEvent
{
    public const CONSISTENT_CHECK = self::class . '.consistent_check';
    public const DATABASE_CHECK = self::class . '.database_check';

    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        private RequestDataBag $requestData,
        private SalesChannelContext $salesChannelContext,
        private DataValidationDefinition $dataValidationDefinition,
        private array $parameters,
    ) {
    }

    public function getRequestData(): RequestDataBag
    {
        return $this->requestData;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getDataValidationDefinition(): DataValidationDefinition
    {
        return $this->dataValidationDefinition;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
