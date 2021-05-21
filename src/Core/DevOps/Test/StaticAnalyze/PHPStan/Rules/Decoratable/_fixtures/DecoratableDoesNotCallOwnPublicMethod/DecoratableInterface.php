<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Test\StaticAnalyze\PHPStan\Rules\Decoratable\_fixtures\DecoratableDoesNotCallOwnPublicMethod;

interface DecoratableInterface
{
    public function run(): void;

    public function build(): void;
}
