<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Struct\Serializer\StructNormalizer;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Assert\Serialization;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Context::class)]
class ContextTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $context = Context::createDefaultContext();

        static::assertInstanceOf(SystemSource::class, $context->getSource());
        static::assertSame(Context::SYSTEM_SCOPE, $context->getScope());
        static::assertSame([], $context->getRuleIds());
        static::assertSame(Defaults::LIVE_VERSION, $context->getVersionId());
    }

    public function testScope(): void
    {
        $context = Context::createDefaultContext();

        static::assertSame(Context::SYSTEM_SCOPE, $context->getScope());

        $context->scope('foo', static function (Context $context): void {
            static::assertSame('foo', $context->getScope());
        });

        static::assertSame(Context::SYSTEM_SCOPE, $context->getScope());
    }

    public function testScopeAddsTemporaryStatesAndRestoresThem(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id'));

        $result = $context->scope(Context::SYSTEM_SCOPE, static function (Context $context): string {
            static::assertSame(Context::SYSTEM_SCOPE, $context->getScope());
            static::assertTrue($context->hasState(Context::SYSTEM_SCOPE_DAL_WRITE_EVENT));

            return 'done';
        }, [Context::SYSTEM_SCOPE_DAL_WRITE_EVENT]);

        static::assertSame('done', $result);
        static::assertSame(Context::USER_SCOPE, $context->getScope());
        static::assertFalse($context->hasState(Context::SYSTEM_SCOPE_DAL_WRITE_EVENT));
    }

    public function testScopeKeepsExistingTemporaryState(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id'));
        $context->addState(Context::SYSTEM_SCOPE_DAL_WRITE_EVENT);

        $context->scope(Context::SYSTEM_SCOPE, static function (Context $context): void {
            static::assertSame(Context::SYSTEM_SCOPE, $context->getScope());
            static::assertTrue($context->hasState(Context::SYSTEM_SCOPE_DAL_WRITE_EVENT));
        }, [Context::SYSTEM_SCOPE_DAL_WRITE_EVENT]);

        static::assertSame(Context::USER_SCOPE, $context->getScope());
        static::assertTrue($context->hasState(Context::SYSTEM_SCOPE_DAL_WRITE_EVENT));
    }

    public function testExplicitSystemScopeInsideScopedStateSuppressesState(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id'));

        $context->scope(Context::SYSTEM_SCOPE, static function (Context $context): void {
            static::assertTrue($context->hasState(Context::SYSTEM_SCOPE_DAL_WRITE_EVENT));

            // This is expected: explicit system-scope opt-ins must not inherit temporary states from the surrounding scope.
            $context->scope(Context::SYSTEM_SCOPE, static function (Context $context): void {
                static::assertFalse($context->hasState(Context::SYSTEM_SCOPE_DAL_WRITE_EVENT));
            });

            static::assertTrue($context->hasState(Context::SYSTEM_SCOPE_DAL_WRITE_EVENT));
        }, [Context::SYSTEM_SCOPE_DAL_WRITE_EVENT]);

        static::assertSame(Context::USER_SCOPE, $context->getScope());
        static::assertFalse($context->hasState(Context::SYSTEM_SCOPE_DAL_WRITE_EVENT));
    }

    public function testVersionChange(): void
    {
        $versionId = Uuid::randomHex();

        $context = Context::createDefaultContext();
        $versionContext = $context->createWithVersionId($versionId);

        static::assertSame(Defaults::LIVE_VERSION, $context->getVersionId());
        static::assertSame($versionId, $versionContext->getVersionId());
    }

    public function testVersionChangeInheritsExtensions(): void
    {
        $context = Context::createDefaultContext();
        $context->addExtension('foo', new ArrayEntity());

        static::assertNotNull($context->getExtension('foo'));

        $versionContext = $context->createWithVersionId(Uuid::randomHex());

        static::assertNotNull($versionContext->getExtension('foo'));
    }

    public function testExtensionsAreStripped(): void
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $discriminator = new ClassDiscriminatorFromClassMetadata($classMetadataFactory);

        $normalizers = [new StructNormalizer(), new ObjectNormalizer($classMetadataFactory, null, null, null, $discriminator), new ArrayDenormalizer()];
        $serializer = new Serializer($normalizers, [new JsonEncoder()]);

        $context = Context::createDefaultContext();

        $context->addExtension('foo', new ArrayEntity());

        $serialized = $serializer->serialize($context, 'json');
        $deserialized = $serializer->deserialize($serialized, Context::class, 'json');

        static::assertInstanceOf(Context::class, $deserialized);

        static::assertEmpty($deserialized->getVars()['extensions']);
        static::assertEquals($context->getSource(), $deserialized->getSource());
        static::assertEquals($context->getRounding(), $deserialized->getRounding());
        static::assertSame($context->getRuleIds(), $deserialized->getRuleIds());
        static::assertSame($context->getVersionId(), $deserialized->getVersionId());
        static::assertSame($context->getScope(), $deserialized->getScope());
        static::assertSame($context->getTaxState(), $deserialized->getTaxState());
        static::assertSame($context->getStates(), $deserialized->getStates());
        static::assertSame($context->getCurrencyId(), $deserialized->getCurrencyId());
        static::assertSame($context->getCurrencyFactor(), $deserialized->getCurrencyFactor());
        static::assertSame($context->getLanguageIdChain(), $deserialized->getLanguageIdChain());
        static::assertSame($context->considerInheritance(), $deserialized->considerInheritance());
    }

    public function testExtensionsAreStrippedOnNativeSerialize(): void
    {
        $context = Context::createDefaultContext();

        $context->addExtension('foo', new ArrayEntity());

        $deserialized = Serialization::assertRoundTrip($context);

        static::assertEmpty($deserialized->getVars()['extensions']);
        static::assertEquals($context->getSource(), $deserialized->getSource());
        static::assertEquals($context->getRounding(), $deserialized->getRounding());
        static::assertSame($context->getRuleIds(), $deserialized->getRuleIds());
        static::assertSame($context->getVersionId(), $deserialized->getVersionId());
        static::assertSame($context->getScope(), $deserialized->getScope());
        static::assertSame($context->getTaxState(), $deserialized->getTaxState());
        static::assertSame($context->getStates(), $deserialized->getStates());
        static::assertSame($context->getCurrencyId(), $deserialized->getCurrencyId());
        static::assertSame($context->getCurrencyFactor(), $deserialized->getCurrencyFactor());
        static::assertSame($context->getLanguageIdChain(), $deserialized->getLanguageIdChain());
        static::assertSame($context->considerInheritance(), $deserialized->considerInheritance());
    }

    public static function twigMethodProviders(): \Generator
    {
        yield 'enableInheritance' => ['{{ context.enableInheritance("print_r") }}'];
        yield 'disableInheritance' => ['{{ context.disableInheritance("print_r") }}'];
        yield 'scope' => ['{{ context.scope("system", "print_r") }}'];
        yield 'tpl' => ['{{ context.enableInheritance("print_r") }}'];
    }

    #[DataProvider('twigMethodProviders')]
    public function testCallableCannotBeCalledFromTwig(string $tpl): void
    {
        $context = Context::createDefaultContext();

        $twig = new Environment(new ArrayLoader([
            'tpl' => $tpl,
        ]));

        $this->expectException(RuntimeError::class);

        $twig->render('tpl', ['context' => $context]);
    }
}
