<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\UseCLIContextRule;

use Shopware\Core\Framework\Context;

/**
 * @internal
 */
class NonRestrictedClass
{
    public function create(): void
    {
        Context::createDefaultContext();
    }
}
