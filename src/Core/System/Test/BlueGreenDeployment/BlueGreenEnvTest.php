<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\BlueGreenDeployment;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class BlueGreenEnvTest extends TestCase
{
    use IntegrationTestBehaviour;

    private $DEFAULT_ENV_VALUE = '1';

    public function testHasCorrectDefaultValue(): void
    {
        static::assertSame($this->DEFAULT_ENV_VALUE, getenv('BLUE_GREEN_DEPLOYMENT'));
    }

    public function testCanChangeValueOfEnvVariable(): void
    {
        // saving the initial value so it can be restored after the test
        $initialValue = (int) getenv('BLUE_GREEN_DEPLOYMENT');

        putenv('BLUE_GREEN_DEPLOYMENT=0');

        static::assertSame('0', getenv('BLUE_GREEN_DEPLOYMENT'));
        putenv('BLUE_GREEN_DEPLOYMENT=' . $initialValue);
    }
}
