<?php declare(strict_types=1);

namespace Shopware\Core\Framework\SystemCheck;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\Category;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;

#[Package('framework')]
abstract class BaseCheck
{
    abstract public function run(): Result;

    abstract public function category(): Category;

    abstract public function name(): string;

    /**
     * Topics the check is about, for example "apps", so checks can be filtered by them.
     *
     * @return list<string>
     */
    public function tags(): array
    {
        return [];
    }

    public function allowedToRunIn(SystemCheckExecutionContext $context): bool
    {
        return \in_array($context, $this->allowedSystemCheckExecutionContexts(), true);
    }

    /**
     * @return array<SystemCheckExecutionContext>
     */
    abstract protected function allowedSystemCheckExecutionContexts(): array;
}
