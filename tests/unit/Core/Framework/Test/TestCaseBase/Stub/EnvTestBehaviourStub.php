<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Test\TestCaseBase\Stub;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;

/**
 * Composes the behaviour the way an integration test does, so its own unit test drives it without
 * composing the trait into the test class. The `#[After]` hook of the trait belongs to PHPUnit and does
 * not fire on this class; the test calls the reset itself.
 *
 * @internal
 */
#[Package('framework')]
final class EnvTestBehaviourStub
{
    use EnvTestBehaviour;
}
