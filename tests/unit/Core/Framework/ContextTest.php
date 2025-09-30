<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Struct\Serializer\StructNormalizer;
use Shopware\Core\Framework\Uuid\Uuid;
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

        $context->scope('foo', function (Context $context): void {
            static::assertSame('foo', $context->getScope());
        });

        static::assertSame(Context::SYSTEM_SCOPE, $context->getScope());
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
        static::assertEqualsCanonicalizing($context->getSource(), $deserialized->getSource());
        static::assertEqualsCanonicalizing($context->getRounding(), $deserialized->getRounding());
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

        $deserialized = unserialize(serialize($context));

        static::assertInstanceOf(Context::class, $deserialized);

        static::assertEmpty($deserialized->getVars()['extensions']);
        static::assertEqualsCanonicalizing($context->getSource(), $deserialized->getSource());
        static::assertEqualsCanonicalizing($context->getRounding(), $deserialized->getRounding());
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
