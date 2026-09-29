<?php declare(strict_types=1);

// deliberately OUTSIDE every test namespace: the rule distinguishes returned production constructors
// (which cannot re-expose a double) from returned test-namespace fixture structs (which can)

namespace SwagFixtureProduction\NoCreateMockWithoutExpectationsRule;

interface ProductionDependency
{
    public function value(): string;
}

class ProductionSut
{
    public function __construct(private readonly ProductionDependency $dependency)
    {
    }

    public function run(): string
    {
        return $this->dependency->value();
    }
}
