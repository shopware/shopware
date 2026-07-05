<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Meta\Metadata;
use Shopware\Core\Framework\App\Validation\ContentSystemBindingSpecificationAppValidator;
use Shopware\Core\Framework\App\Validation\Error\ContentSystemBindingSpecificationSchemaError;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\ResolvedBindingSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\YamlBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDto;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\YamlTypeLoader;
use Shopware\Core\Framework\Context;

/**
 * @internal
 */
#[CoversClass(ContentSystemBindingSpecificationAppValidator::class)]
class ContentSystemBindingSpecificationAppValidatorTest extends TestCase
{
    #[TestDox('turns a canonicalization failure in a binding load into a schema error instead of throwing')]
    public function testCanonicalizationFailureBecomesSchemaError(): void
    {
        $loader = static::createStub(YamlBindingSpecificationLoader::class);
        $loader->method('loadDtosFromDirectory')->willThrowException(
            ContentSystemException::bindingSpecificationCanonicalizationFailed('bad', 'unexpected shape')
        );
        $loader->method('loadInlineDtosFromTypeDirectory')->willReturn([]);

        $errors = $this->validator($loader)->validate($this->manifest(), Context::createDefaultContext());

        static::assertCount(1, $errors->getElements());
        $error = $errors->first();
        static::assertInstanceOf(ContentSystemBindingSpecificationSchemaError::class, $error);
        static::assertStringContainsString('unexpected shape', $error->getMessage());
    }

    #[TestDox('aggregates a violation from each authoring form into one schema error, so a failing standalone load does not suppress the inline one')]
    public function testStandaloneAndInlineFailuresAreCollectedIndependently(): void
    {
        $loader = static::createStub(YamlBindingSpecificationLoader::class);
        $loader->method('loadDtosFromDirectory')->willThrowException(
            ContentSystemException::bindingSpecificationLoadFailed('standalone.yaml', 'broken')
        );
        $loader->method('loadInlineDtosFromTypeDirectory')->willThrowException(
            ContentSystemException::bindingSpecificationCanonicalizationFailed('inline', 'broken')
        );

        $errors = $this->validator($loader)->validate($this->manifest(), Context::createDefaultContext());

        // ErrorCollection keys by message key, so both violations must ride ONE aggregated error.
        static::assertCount(1, $errors->getElements());
        $error = $errors->first();
        static::assertInstanceOf(ContentSystemBindingSpecificationSchemaError::class, $error);
        static::assertStringContainsString('standalone.yaml', $error->getMessage());
        static::assertStringContainsString('Cannot canonicalize binding specification "inline"', $error->getMessage());
    }

    #[TestDox('reports a schema error when an inline binding and a standalone binding share a bare id')]
    public function testCrossFormIdCollisionBecomesSchemaError(): void
    {
        $standalone = new ResolvedBindingSpecificationDto('shared-id', 'app:DemoApp', new BindingSpecificationDto('image', 'Standalone', null, null));
        $inline = new ResolvedBindingSpecificationDto('shared-id', 'app:DemoApp', new BindingSpecificationDto('image', 'Inline', null, null));

        $loader = static::createStub(YamlBindingSpecificationLoader::class);
        $loader->method('loadDtosFromDirectory')->willReturn([$standalone]);
        $loader->method('loadInlineDtosFromTypeDirectory')->willReturn([$inline]);

        $errors = $this->validator($loader)->validate($this->manifest(), Context::createDefaultContext());

        static::assertCount(1, $errors->getElements());
        $error = $errors->first();
        static::assertInstanceOf(ContentSystemBindingSpecificationSchemaError::class, $error);
        static::assertStringContainsString('shared-id', $error->getMessage());
    }

    #[TestDox('falls back to an empty type overlay when the app types fail to load, without adding an error or throwing')]
    public function testMalformedAppTypesFallBackToEmptyOverlay(): void
    {
        $typeLoader = static::createStub(YamlTypeLoader::class);
        $typeLoader->method('loadOverlayFromDirectory')->willThrowException(
            ContentSystemException::elementTypeLoadFailed('type.yaml', 'broken')
        );

        $loader = static::createStub(YamlBindingSpecificationLoader::class);
        $loader->method('loadDtosFromDirectory')->willReturn([]);
        $loader->method('loadInlineDtosFromTypeDirectory')->willReturn([]);

        // Malformed app types are the element-type validator's error to report; this validator must not add one
        // and must not let the type-load failure escape.
        $errors = $this->validator($loader, $typeLoader)->validate($this->manifest(), Context::createDefaultContext());

        static::assertCount(0, $errors->getElements());
    }

    #[TestDox('reports a schema error when an app binding promotes a type the registry already promotes')]
    public function testPromotedConflictWithRegistryBecomesSchemaError(): void
    {
        $appPromoted = new ResolvedBindingSpecificationDto('app-promoted', 'app:DemoApp', new BindingSpecificationDto('Sw:Media:Image', 'App', null, null, true));

        $loader = static::createStub(YamlBindingSpecificationLoader::class);
        $loader->method('loadDtosFromDirectory')->willReturn([$appPromoted]);
        $loader->method('loadInlineDtosFromTypeDirectory')->willReturn([]);

        $registry = $this->registryWith(new BindingSpecification('from-media-library', 'Sw:Media:Image', 'Registered', [], [], 'core', true));

        $errors = $this->validator($loader, null, $registry)->validate($this->manifest(), Context::createDefaultContext());

        static::assertCount(1, $errors->getElements());
        $error = $errors->first();
        static::assertInstanceOf(ContentSystemBindingSpecificationSchemaError::class, $error);
        static::assertStringContainsString('app-promoted', $error->getMessage());
        static::assertStringContainsString('Sw:Media:Image', $error->getMessage());
        static::assertStringContainsString('core:from-media-library', $error->getMessage());
    }

    #[TestDox('reports a schema error when the app promotes one type twice across its standalone and inline forms')]
    public function testAppInternalDoublePromotionBecomesSchemaError(): void
    {
        $standalonePromoted = new ResolvedBindingSpecificationDto('standalone-promoted', 'app:DemoApp', new BindingSpecificationDto('Sw:Media:Image', 'Standalone', null, null, true));
        $inlinePromoted = new ResolvedBindingSpecificationDto('inline-promoted', 'app:DemoApp', new BindingSpecificationDto('Sw:Media:Image', 'Inline', null, null, true));

        $loader = static::createStub(YamlBindingSpecificationLoader::class);
        $loader->method('loadDtosFromDirectory')->willReturn([$standalonePromoted]);
        $loader->method('loadInlineDtosFromTypeDirectory')->willReturn([$inlinePromoted]);

        $errors = $this->validator($loader, null, $this->registryWith())->validate($this->manifest(), Context::createDefaultContext());

        static::assertCount(1, $errors->getElements());
        $error = $errors->first();
        static::assertInstanceOf(ContentSystemBindingSpecificationSchemaError::class, $error);
        static::assertStringContainsString('inline-promoted', $error->getMessage());
        static::assertStringContainsString('standalone-promoted', $error->getMessage());
        static::assertStringContainsString('Sw:Media:Image', $error->getMessage());
    }

    #[TestDox('does not report a conflict when the registry promotes a different type than the app binding')]
    public function testNoPromotedConflictWhenRegistryPromotesAnotherType(): void
    {
        $appPromoted = new ResolvedBindingSpecificationDto('app-promoted', 'app:DemoApp', new BindingSpecificationDto('Sw:Media:Image', 'App', null, null, true));

        $loader = static::createStub(YamlBindingSpecificationLoader::class);
        $loader->method('loadDtosFromDirectory')->willReturn([$appPromoted]);
        $loader->method('loadInlineDtosFromTypeDirectory')->willReturn([]);

        $registry = $this->registryWith(new BindingSpecification('from-product', 'Sw:Product:Box', 'Registered', [], [], 'core', true));

        $errors = $this->validator($loader, null, $registry)->validate($this->manifest(), Context::createDefaultContext());

        static::assertCount(0, $errors->getElements());
    }

    private function validator(YamlBindingSpecificationLoader $loader, ?YamlTypeLoader $typeLoader = null, ?AbstractContentSystemBindingSpecificationRegistry $registry = null): ContentSystemBindingSpecificationAppValidator
    {
        $typeLoader ??= static::createStub(YamlTypeLoader::class);
        $registry ??= $this->registryWith();

        return new ContentSystemBindingSpecificationAppValidator($loader, $typeLoader, $registry);
    }

    private function registryWith(BindingSpecification ...$specifications): AbstractContentSystemBindingSpecificationRegistry
    {
        $all = [];
        foreach ($specifications as $specification) {
            $all[$specification->source() . ':' . $specification->id()] = $specification;
        }

        $registry = static::createStub(AbstractContentSystemBindingSpecificationRegistry::class);
        $registry->method('all')->willReturn($all);

        return $registry;
    }

    private function manifest(): Manifest
    {
        $metadata = static::createStub(Metadata::class);
        $metadata->method('getName')->willReturn('DemoApp');

        $manifest = static::createStub(Manifest::class);
        $manifest->method('getMetadata')->willReturn($metadata);
        $manifest->method('getPath')->willReturn('/app');

        return $manifest;
    }
}
