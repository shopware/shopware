<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Test\StaticAnalyze\PHPStan\Rules\Decoratable\_fixtures\DecoratableDoesNotCallOwnPublicMethod;

/**
 * @internal
 */
class NotTaggedClassIsAllowedToCallOwnPublicMethod implements DecoratableInterface
{
    public function run(): void
    {
        $this->build();
    }

    public function build(): void
    {
    }
}
