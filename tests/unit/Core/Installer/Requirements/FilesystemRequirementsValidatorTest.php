<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Requirements;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\Requirements\FilesystemRequirementsValidator;
use Shopware\Core\Installer\Requirements\Struct\PathCheck;
use Shopware\Core\Installer\Requirements\Struct\RequirementCheck;
use Shopware\Core\Installer\Requirements\Struct\RequirementsCheckCollection;

/**
 * @internal
 */
#[CoversClass(FilesystemRequirementsValidator::class)]
class FilesystemRequirementsValidatorTest extends TestCase
{
    public function testValidate(): void
    {
        mkdir(__DIR__ . '/fixtures');
        mkdir(__DIR__ . '/fixtures/var');
        mkdir(__DIR__ . '/fixtures/var/log');
        mkdir(__DIR__ . '/fixtures/var/cache');
        mkdir(__DIR__ . '/fixtures/public');
        mkdir(__DIR__ . '/fixtures/config');
        mkdir(__DIR__ . '/fixtures/config/jwt');

        $validator = new FilesystemRequirementsValidator(__DIR__ . '/fixtures');

        $checks = new RequirementsCheckCollection();
        $checks = $validator->validateRequirements($checks);

        static::assertCount(5, $checks->getElements());

        static::assertInstanceOf(PathCheck::class, $checks->getElements()[0]);
        static::assertSame('.', $checks->getElements()[0]->getName());
        static::assertSame(RequirementCheck::STATUS_SUCCESS, $checks->getElements()[0]->getStatus());

        static::assertInstanceOf(PathCheck::class, $checks->getElements()[1]);
        static::assertSame('var/log/', $checks->getElements()[1]->getName());
        static::assertSame(RequirementCheck::STATUS_SUCCESS, $checks->getElements()[1]->getStatus());

        static::assertInstanceOf(PathCheck::class, $checks->getElements()[2]);
        static::assertSame('var/cache/', $checks->getElements()[2]->getName());
        static::assertSame(RequirementCheck::STATUS_SUCCESS, $checks->getElements()[2]->getStatus());

        static::assertInstanceOf(PathCheck::class, $checks->getElements()[3]);
        static::assertSame('public/', $checks->getElements()[3]->getName());
        static::assertSame(RequirementCheck::STATUS_SUCCESS, $checks->getElements()[3]->getStatus());

        static::assertInstanceOf(PathCheck::class, $checks->getElements()[4]);
        static::assertSame('config/jwt/', $checks->getElements()[4]->getName());
        static::assertSame(RequirementCheck::STATUS_SUCCESS, $checks->getElements()[4]->getStatus());

        rmdir(__DIR__ . '/fixtures/var/log');
        rmdir(__DIR__ . '/fixtures/var/cache');
        rmdir(__DIR__ . '/fixtures/var');
        rmdir(__DIR__ . '/fixtures/public');
        rmdir(__DIR__ . '/fixtures/config/jwt');
        rmdir(__DIR__ . '/fixtures/config');
        rmdir(__DIR__ . '/fixtures');
    }

    public function testValidateNotExistingDirectories(): void
    {
        $validator = new FilesystemRequirementsValidator('/not/existing/path');

        $checks = new RequirementsCheckCollection();
        $checks = $validator->validateRequirements($checks);

        static::assertCount(5, $checks->getElements());

        static::assertInstanceOf(PathCheck::class, $checks->getElements()[0]);
        static::assertSame('.', $checks->getElements()[0]->getName());
        static::assertSame(RequirementCheck::STATUS_ERROR, $checks->getElements()[0]->getStatus());

        static::assertInstanceOf(PathCheck::class, $checks->getElements()[1]);
        static::assertSame('var/log/', $checks->getElements()[1]->getName());
        static::assertSame(RequirementCheck::STATUS_ERROR, $checks->getElements()[1]->getStatus());

        static::assertInstanceOf(PathCheck::class, $checks->getElements()[2]);
        static::assertSame('var/cache/', $checks->getElements()[2]->getName());
        static::assertSame(RequirementCheck::STATUS_ERROR, $checks->getElements()[2]->getStatus());

        static::assertInstanceOf(PathCheck::class, $checks->getElements()[3]);
        static::assertSame('public/', $checks->getElements()[3]->getName());
        static::assertSame(RequirementCheck::STATUS_ERROR, $checks->getElements()[3]->getStatus());

        static::assertInstanceOf(PathCheck::class, $checks->getElements()[4]);
        static::assertSame('config/jwt/', $checks->getElements()[4]->getName());
        static::assertSame(RequirementCheck::STATUS_ERROR, $checks->getElements()[4]->getStatus());
    }
}
