<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\TestCaseBase;

use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
class EnvTestBehaviourTest extends TestCase
{
    use EnvTestBehaviour;
    use KernelTestBehaviour;

    public function testResettingDropsAContainerThatResolvedTheChangedEnvVar(): void
    {
        $appUrl = (string) EnvironmentHelper::getVariable('APP_URL');

        $this->setEnvVars(['APP_URL' => 'https://env-test-behaviour.test']);
        KernelLifecycleManager::bootKernel();

        static::assertSame('https://env-test-behaviour.test', static::getContainer()->getParameter('APP_URL'));

        $this->resetEnvVars();

        static::assertSame($appUrl, static::getContainer()->getParameter('APP_URL'));
    }
}
