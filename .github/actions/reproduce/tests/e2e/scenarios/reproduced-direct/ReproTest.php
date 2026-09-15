<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Repro;

use PHPUnit\Framework\TestCase;

/**
 * E2E harness scenario (synthetic): a direct/PHPUnit leg that deterministically fails on the symptom
 * assertion so the direct executor → output-classifier → verdict → comment path is exercised against
 * a real PHP/PHPUnit run. The marker in the failure message is matched by the plan's symptom_pattern,
 * so the failure is classified `reproduced` (not a setup failure). Not a real bug — a path exercise.
 */
class ReproTest extends TestCase
{
    public function testSymptom(): void
    {
        static::assertTrue(false, 'E2E_SYMPTOM_MARKER: synthetic reproduced-path check');
    }
}
