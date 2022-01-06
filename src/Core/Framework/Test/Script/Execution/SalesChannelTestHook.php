<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Script\Execution;

use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
class SalesChannelTestHook extends Hook implements SalesChannelContextAware
{
    use SalesChannelContextAwareTrait;

    private string $name;

    private static array $serviceIds;

    public function __construct(string $name, SalesChannelContext $context, array $data = [], array $serviceIds = [])
    {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
        $this->name = $name;
        self::$serviceIds = $serviceIds;

        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    public static function getServiceIds(): array
    {
        return self::$serviceIds;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
