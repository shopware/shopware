<?php

namespace Shopware\Core\System\SalesChannel\Event;

use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SwitchContextEvent
{
    public const CONSISTENT_CHECK = self::class . '.consistent_check';
    public const DATABASE_CHECK = self::class . '.database_check';

    public function __construct(
        public RequestDataBag $data,
        public DataValidationDefinition $definition,
        public array $parameters,
        public SalesChannelContext $context
    ) {
    }
}
