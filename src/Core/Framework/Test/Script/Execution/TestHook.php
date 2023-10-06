<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Script\Execution;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Script\Execution\Hook;

/**
 * @internal
 */
#[\AllowDynamicProperties]
class TestHook extends Hook
{
    /**
     * @var array<string>
     */
    private static array $serviceIds;

    /**
     * @var array<string>
     */
    private static array $deprecatedServices;

    /**
     * @param array<string> $serviceIds
     * @param array<string> $deprecatedServices
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly string $name,
        Context $context,
        array $data = [],
        array $serviceIds = [],
        array $deprecatedServices = []
    ) {
        parent::__construct($context);
        self::$serviceIds = $serviceIds;
        self::$deprecatedServices = $deprecatedServices;

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

    /**
     * @return array<string>
     */
    public static function getDeprecatedServices(): array
    {
        return self::$deprecatedServices;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
