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

    public function __construct(string $name, Context $context, array $data = [], array $serviceIds = [])
    {
        parent::__construct($context);
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
