<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\BlueGreenDeployment;

use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class BlueGreenEnvTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const DEFAULT_ENV_VALUE = '1';

    public function testHasCorrectDefaultValue(): void
    {
        static::assertSame(self::DEFAULT_ENV_VALUE, (string) EnvironmentHelper::getVariable('BLUE_GREEN_DEPLOYMENT', self::DEFAULT_ENV_VALUE));
    }

    public function testCanChangeValueOfEnvVariable(): void
    {
        // saving the initial value so it can be restored after the test
        $initialValue = (string) EnvironmentHelper::getVariable('BLUE_GREEN_DEPLOYMENT', self::DEFAULT_ENV_VALUE);

        $_SERVER['BLUE_GREEN_DEPLOYMENT'] = '0';

        static::assertSame('0', (string) EnvironmentHelper::getVariable('BLUE_GREEN_DEPLOYMENT', self::DEFAULT_ENV_VALUE));
        $_SERVER['BLUE_GREEN_DEPLOYMENT'] = $initialValue;
    }
}
