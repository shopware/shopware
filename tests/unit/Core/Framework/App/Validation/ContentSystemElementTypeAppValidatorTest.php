<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\ContentSystemElementTypeAppValidator;
use Shopware\Core\Framework\App\Validation\Error\ContentSystemElementTypeSchemaError;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\YamlTypeLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * @internal
 */
#[CoversClass(ContentSystemElementTypeAppValidator::class)]
class ContentSystemElementTypeAppValidatorTest extends TestCase
{
    #[TestDox('returns empty error collection when loader succeeds')]
    public function testValidateReturnsEmptyCollectionOnSuccess(): void
    {
        $loader = $this->createMock(YamlTypeLoader::class);
        $loader->expects($this->atLeastOnce())
            ->method('load')
            ->with(static::callback(function (Filesystem $fs): bool {
                return $fs->location === '/app/path/Resources/content-system/types';
            }));

        $manifest = static::createStub(Manifest::class);
        $manifest->method('getPath')->willReturn('/app/path');

        $validator = new ContentSystemElementTypeAppValidator($loader);
        $errors = $validator->validate($manifest, Context::createDefaultContext());

        static::assertCount(0, $errors->getElements());
    }

    #[TestDox('returns error when loader throws ContentSystemException')]
    public function testValidateReturnsErrorOnContentSystemException(): void
    {
        $loader = static::createStub(YamlTypeLoader::class);
        $loader->method('load')
            ->willThrowException(ContentSystemException::elementTypeLoadFailed('broken.yaml', 'Invalid YAML syntax'));

        $manifest = static::createStub(Manifest::class);
        $manifest->method('getPath')->willReturn('/app/path');

        $validator = new ContentSystemElementTypeAppValidator($loader);
        $errors = $validator->validate($manifest, Context::createDefaultContext());

        static::assertCount(1, $errors->getElements());

        $error = $errors->first();
        static::assertInstanceOf(ContentSystemElementTypeSchemaError::class, $error);
        static::assertStringContainsString('/app/path/Resources/content-system/types', $error->getMessage());
        static::assertSame('manifest-invalid-element-type-schema', $error->getMessageKey());
    }
}
