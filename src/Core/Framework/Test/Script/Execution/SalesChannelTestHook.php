<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Script\Execution;

use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[\AllowDynamicProperties]
class SalesChannelTestHook extends Hook implements SalesChannelContextAware
{
    use SalesChannelContextAwareTrait;

    /**
     * @var array<string>
     */
    private static array $serviceIds;

    /**
     * @param array<string> $serviceIds
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly string $name,
        SalesChannelContext $context,
        array $data = [],
        array $serviceIds = []
    ) {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
        self::$serviceIds = $serviceIds;

        foreach ($data as $key => $value) {
            $this->$key = $value; /* @phpstan-ignore-line */
        }
    }

    /**
     * @return array<string>
     */
    public static function getServiceIds(): array
    {
        return self::$serviceIds;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
