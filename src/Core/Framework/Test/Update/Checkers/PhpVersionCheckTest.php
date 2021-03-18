<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Update\Checkers;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Update\Checkers\PhpVersionCheck;

class PhpVersionCheckTest extends TestCase
{
    public function testPhp74Min(): void
    {
        $validationResult = (new PhpVersionCheck())
            ->check('7.4')
            ->jsonSerialize();

        static::assertTrue($validationResult['result']);
    }

    public function testPhp8Support(): void
    {
        $validationResult = (new PhpVersionCheck())
            ->check('8.0.0')
            ->jsonSerialize();

        if (\PHP_VERSION_ID >= 80000) {
            static::assertTrue($validationResult['result']);
        } else {
            static::assertFalse($validationResult['result']);
        }
    }

    public function testSupports(): void
    {
        static::assertTrue((new PhpVersionCheck())->supports('phpversion'));
        static::assertFalse((new PhpVersionCheck())->supports('mysqlversion'));
        static::assertFalse((new PhpVersionCheck())->supports('licensecheck'));
        static::assertFalse((new PhpVersionCheck())->supports('writable'));
        static::assertFalse((new PhpVersionCheck())->supports(''));
    }
}
