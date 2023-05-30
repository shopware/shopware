<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Script\Execution;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Script\Execution\Hook;

/**
 * @internal
 */
class TestHook extends Hook
{
    private static array $serviceIds;

    private static array $deprecatedServices;

    /**
     * @param array<string> $serviceIds
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

    public static function getServiceIds(): array
    {
        return self::$serviceIds;
    }

    public static function getDeprecatedServices(): array
    {
        return self::$deprecatedServices;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
