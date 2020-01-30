<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Update\Checkers;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Update\Checkers\PhpVersionCheck;

class PhpVersionCheckTest extends TestCase
{
    public function testPhp72(): void
    {
        $validationResult = (new PhpVersionCheck())
            ->check('7.2')
            ->jsonSerialize();

        static::assertTrue($validationResult['result']);
    }

    public function testPhp8Fails(): void
    {
        $validationResult = (new PhpVersionCheck())
            ->check('8.0.0')
            ->jsonSerialize();

        static::assertFalse($validationResult['result']);
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
