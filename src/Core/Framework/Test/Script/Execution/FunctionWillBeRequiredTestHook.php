<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Script\Execution;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Script\Execution\OptionalFunctionHook;

/**
 * @internal
 */
class FunctionWillBeRequiredTestHook extends OptionalFunctionHook
{
    private string $name;

    public function __construct(string $name, Context $context, array $data)
    {
        $this->name = $name;

        parent::__construct($context);

        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    public function getFunctionName(): string
    {
        return 'test';
    }

    public static function getServiceIds(): array
    {
        return [];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public static function willBeRequiredInVersion(): ?string
    {
        return 'v6.5.0.0';
    }
}
