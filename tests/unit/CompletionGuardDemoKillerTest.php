<?php declare(strict_types=1);

namespace Shopware\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal Demo for the TestBootstrapper completion guard — DO NOT MERGE.
 * Simulates code under test terminating the PHPUnit process with a success
 * exit code (the #18560 incident class): without the guard the unit job goes
 * green after running only a random prefix of the suite.
 */
#[Package('framework')]
class CompletionGuardDemoKillerTest extends TestCase
{
    public function testExitZeroKillsThePhpunitProcess(): void
    {
        exit(0);
    }
}
