<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Script\Execution;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Script\Execution\Hook;

/**
 * @internal
 */
class TestHook extends Hook
{
    private string $name;

    private static array $serviceIds;

    private static array $deprecatedServices;

    public function __construct(
        string $name,
        Context $context,
        array $data = [],
        array $serviceIds = [],
        array $deprecatedServices = []
    ) {
        parent::__construct($context);
        $this->name = $name;
        self::$serviceIds = $serviceIds;
        self::$deprecatedServices = $deprecatedServices;

        foreach ($data as $key => $value) {
            $this->$key = $value;
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
