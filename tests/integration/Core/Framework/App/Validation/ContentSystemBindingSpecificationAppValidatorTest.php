<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Validation;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\ContentSystemBindingSpecificationAppValidator;
use Shopware\Core\Framework\App\Validation\Error\ContentSystemBindingSpecificationSchemaError;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class ContentSystemBindingSpecificationAppValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    #[TestDox('rejects a binding specification whose loader-configured produced type is not assignable to the declared reference property, through the real app manifest validation flow')]
    public function testRejectsConfiguredProducedTypeMismatch(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/binding-specification-type-mismatch/manifest.xml');

        $validator = static::getContainer()->get(ContentSystemBindingSpecificationAppValidator::class);
        static::assertInstanceOf(ContentSystemBindingSpecificationAppValidator::class, $validator);

        $errors = $validator->validate($manifest, Context::createDefaultContext());

        static::assertCount(1, $errors->getElements());
        $error = $errors->first();
        static::assertInstanceOf(ContentSystemBindingSpecificationSchemaError::class, $error);
        static::assertSame('manifest-invalid-binding-specification-schema', $error->getMessageKey());
        static::assertStringContainsString('resolves entry "media"', $error->getMessage());
        static::assertStringContainsString(
            'not assignable to the declared property type "Shopware\Core\Content\Media\MediaEntity"',
            $error->getMessage()
        );
    }

    #[TestDox('adds no error for a well-formed, type-consistent binding specification, through the real app manifest validation flow')]
    public function testAddsNoErrorForValidBindingSpecification(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/binding-specification-valid/manifest.xml');

        $validator = static::getContainer()->get(ContentSystemBindingSpecificationAppValidator::class);
        static::assertInstanceOf(ContentSystemBindingSpecificationAppValidator::class, $validator);

        $errors = $validator->validate($manifest, Context::createDefaultContext());

        static::assertCount(0, $errors->getElements());
    }
}
