<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\TestCaseBase;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
class EnvTestBehaviourTest extends TestCase
{
    use EnvTestBehaviour;
    use KernelTestBehaviour;

    private const REDIRECTED_APP_URL = 'https://env-test-behaviour.test';

    public function testTheContainerResolvesAChangedEnvVarAgain(): void
    {
        $this->setEnvVars(['APP_URL' => self::REDIRECTED_APP_URL]);

        static::assertSame(self::REDIRECTED_APP_URL, static::getContainer()->getParameter('APP_URL'));
    }

    public function testResettingDropsTheContainerThatSawTheChangedEnvVar(): void
    {
        $appUrl = static::getContainer()->getParameter('APP_URL');
        $container = static::getContainer();

        $this->setEnvVars(['APP_URL' => self::REDIRECTED_APP_URL]);
        static::getContainer()->getParameter('APP_URL');

        $this->resetEnvVars();

        static::assertNotSame($container, static::getContainer());
        static::assertSame($appUrl, static::getContainer()->getParameter('APP_URL'));
    }
}
