<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution;

use Shopware\Core\Framework\Context;

abstract class Hook
{
    protected Context $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    abstract public function getServiceIds(): array;

    abstract public function getName(): string;
}
