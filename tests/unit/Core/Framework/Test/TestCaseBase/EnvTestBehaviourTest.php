<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Test\TestCaseBase;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
#[CoversNothing]
class EnvTestBehaviourTest extends TestCase
{
    use EnvTestBehaviour;

    private const PREVIOUSLY_UNSET = 'ENV_TEST_BEHAVIOUR_TEST_PREVIOUSLY_UNSET';

    private const PREVIOUSLY_SET = 'ENV_TEST_BEHAVIOUR_TEST_PREVIOUSLY_SET';

    protected function tearDown(): void
    {
        unset($_SERVER[self::PREVIOUSLY_UNSET], $_ENV[self::PREVIOUSLY_UNSET]);
        unset($_SERVER[self::PREVIOUSLY_SET], $_ENV[self::PREVIOUSLY_SET]);
        putenv(self::PREVIOUSLY_UNSET);
        putenv(self::PREVIOUSLY_SET);
    }

    public function testResetRemovesAVariableThatWasUnsetBefore(): void
    {
        static::assertFalse(getenv(self::PREVIOUSLY_UNSET));

        $this->setEnvVars([self::PREVIOUSLY_UNSET => 'redirected']);

        static::assertSame('redirected', getenv(self::PREVIOUSLY_UNSET));
        static::assertSame('redirected', $_SERVER[self::PREVIOUSLY_UNSET]);
        static::assertSame('redirected', $_ENV[self::PREVIOUSLY_UNSET]);

        $this->resetEnvVars();

        // getenv() reads the real process environment, which spawned processes inherit;
        // "" instead of false here means the variable would leak into them
        static::assertFalse(getenv(self::PREVIOUSLY_UNSET));
        static::assertArrayNotHasKey(self::PREVIOUSLY_UNSET, $_SERVER);
        static::assertArrayNotHasKey(self::PREVIOUSLY_UNSET, $_ENV);
    }

    public function testResetRestoresAVariableThatWasSetBefore(): void
    {
        $original = 'original-' . bin2hex(random_bytes(4));
        $_SERVER[self::PREVIOUSLY_SET] = $original;
        $_ENV[self::PREVIOUSLY_SET] = $original;
        putenv(self::PREVIOUSLY_SET . '=' . $original);

        $this->setEnvVars([self::PREVIOUSLY_SET => 'redirected']);

        static::assertSame('redirected', getenv(self::PREVIOUSLY_SET));

        $this->resetEnvVars();

        static::assertSame($original, getenv(self::PREVIOUSLY_SET));
        static::assertSame($original, $_SERVER[self::PREVIOUSLY_SET]);
        static::assertSame($original, $_ENV[self::PREVIOUSLY_SET]);
    }

    public function testNullValueRemovesTheVariableForTheDurationOfTheTest(): void
    {
        $original = 'original-' . bin2hex(random_bytes(4));
        $_SERVER[self::PREVIOUSLY_SET] = $original;
        $_ENV[self::PREVIOUSLY_SET] = $original;
        putenv(self::PREVIOUSLY_SET . '=' . $original);

        $this->setEnvVars([self::PREVIOUSLY_SET => null]);

        static::assertFalse(getenv(self::PREVIOUSLY_SET));
        static::assertArrayNotHasKey(self::PREVIOUSLY_SET, $_SERVER);
        static::assertArrayNotHasKey(self::PREVIOUSLY_SET, $_ENV);

        $this->resetEnvVars();

        static::assertSame($original, getenv(self::PREVIOUSLY_SET));
        static::assertSame($original, $_SERVER[self::PREVIOUSLY_SET]);
    }

    public function testSetEnvVarsKeepsTheOriginalValueAcrossRepeatedCalls(): void
    {
        $_SERVER[self::PREVIOUSLY_SET] = 'original';
        $_ENV[self::PREVIOUSLY_SET] = 'original';
        putenv(self::PREVIOUSLY_SET . '=original');

        $this->setEnvVars([self::PREVIOUSLY_SET => 'first']);
        $this->setEnvVars([self::PREVIOUSLY_SET => 'second']);

        $this->resetEnvVars();

        static::assertSame('original', getenv(self::PREVIOUSLY_SET));
    }
}
