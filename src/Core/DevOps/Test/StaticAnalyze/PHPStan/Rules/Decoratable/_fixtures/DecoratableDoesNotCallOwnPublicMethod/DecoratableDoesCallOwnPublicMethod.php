<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Test\StaticAnalyze\PHPStan\Rules\Decoratable\_fixtures\DecoratableDoesNotCallOwnPublicMethod;

use Shopware\Core\System\Annotation\Concept\ExtensionPattern\Decoratable;

/**
 * @Decoratable
 */
class DecoratableDoesCallOwnPublicMethod implements DecoratableInterface
{
    public function run(): void
    {
        $this->build();
    }

    public function build(): void
    {
        $this->test();
    }

    private function test(): void
    {
    }
}
