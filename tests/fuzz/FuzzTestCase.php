<?php declare(strict_types=1);

namespace Shopware\Tests\Fuzz;

use Eris\TestTrait;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;

/**
 * Base class for property-based ("fuzz") tests under tests/fuzz. Centralizes the one
 * required trait usage (Eris\TestTrait provides forAll()) so individual test classes don't
 * each need their own `use` statement. See .agents/skills/shopware-fuzz-tests.
 *
 * @internal
 */
#[Package('framework')]
abstract class FuzzTestCase extends TestCase
{
    use TestTrait;
}
