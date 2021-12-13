<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Script\Execution;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Script\Execution\Hook;

/**
 * @internal (flag:FEATURE_NEXT_17441)
 */
class TestHook extends Hook
{
    private string $name;

    private array $serviceIds;

    public function __construct(string $name, Context $context, array $data = [], array $serviceIds = [])
    {
        parent::__construct($context);
        $this->name = $name;
        $this->serviceIds = $serviceIds;

        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    public function getServiceIds(): array
    {
        return $this->serviceIds;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
