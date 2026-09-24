<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\FuturePropertyCompatibilityRule;

class FuturePropertyConsumer extends FutureNarrowedProperty
{
    public function assignNull(): void
    {
        $this->value = null;
    }

    public function assignString(): void
    {
        $this->value = 'value';
    }
}
