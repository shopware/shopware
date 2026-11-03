<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Aggregate\CategoryContentLayout\CategoryMappingCandidateProvider;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageContentLayout\LandingPageMappingCandidateProvider;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductMappingCandidateProvider;
use Shopware\Core\Framework\ContentSystem\Mapping\Provider\AbstractMappingCandidateProvider;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\MappingCandidateProviderCompilerPass;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\ContentSystem\Mapping\CustomFieldMappingCandidateProvider;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MappingCandidateProviderCompilerPass::class)]
class MappingCandidateProviderCompilerPassTest extends TestCase
{
    /**
     * @param array<string, string> $parameters
     */
    #[DataProvider('providerClassParameters')]
    public function testCompilesProviderClassesReferencedThroughParameters(array $parameters): void
    {
        $container = new ContainerBuilder();
        foreach ($parameters as $name => $value) {
            $container->setParameter($name, $value);
        }
        $container->setDefinition('parameterized', (new Definition('%plugin.provider.class%'))
            ->setPublic(true)
            ->addTag('content_system.mapping_candidate_provider'));
        $container->addCompilerPass(new MappingCandidateProviderCompilerPass());

        $container->compile();

        static::assertInstanceOf(FirstRegisteredProvider::class, $container->get('parameterized'));
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function providerClassParameters(): iterable
    {
        yield 'direct class parameter' => [['plugin.provider.class' => FirstRegisteredProvider::class]];
        yield 'nested class parameter' => [[
            'plugin.provider.class' => '%plugin.provider.implementation%',
            'plugin.provider.implementation' => FirstRegisteredProvider::class,
        ]];
        yield 'class assembled from a namespace parameter' => [[
            'plugin.provider.class' => '%plugin.provider.namespace%\\FirstRegisteredProvider',
            'plugin.provider.namespace' => __NAMESPACE__,
        ]];
    }

    /**
     * @param class-string<AbstractMappingCandidateProvider> $providerClass
     */
    #[DataProvider('parameterizedCollisions')]
    public function testParameterizedProvidersStillParticipateInCollisionChecks(string $providerClass, string $firstOwner, string $secondOwner): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('plugin.provider.class', $providerClass);
        $container->setDefinition('first', (new Definition(FirstRegisteredProvider::class))->addTag('content_system.mapping_candidate_provider'));
        $container->setDefinition('parameterized', (new Definition('%plugin.provider.class%'))->addTag('content_system.mapping_candidate_provider'));
        $this->expectExceptionObject(DependencyInjectionException::mappingCandidateProviderKeyCollision(
            'product',
            'product.name',
            $firstOwner,
            $secondOwner,
        ));

        (new MappingCandidateProviderCompilerPass())->process($container);
    }

    /**
     * @return iterable<string, array{class-string<AbstractMappingCandidateProvider>, string, string}>
     */
    public static function parameterizedCollisions(): iterable
    {
        yield 'duplicate declaration for the same root' => [SecondRegisteredProvider::class, 'first', 'parameterized'];
        yield 'wildcard declaration conflicts with an existing root declaration' => [WildcardRegisteredProvider::class, 'parameterized', 'first'];
    }

    #[DataProvider('invalidProviderClassParameters')]
    public function testRejectsInvalidResolvedProviderClasses(mixed $class): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('plugin.provider.class', $class);
        $container->setDefinition('parameterized', (new Definition('%plugin.provider.class%'))->addTag('content_system.mapping_candidate_provider'));
        $this->expectExceptionObject(DependencyInjectionException::taggedServiceHasWrongType(
            'parameterized',
            'content_system.mapping_candidate_provider',
            AbstractMappingCandidateProvider::class,
        ));

        (new MappingCandidateProviderCompilerPass())->process($container);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidProviderClassParameters(): iterable
    {
        yield 'null is not a provider class' => [null];
        yield 'an array is not a provider class' => [[FirstRegisteredProvider::class]];
        yield 'a number is not a provider class' => [42];
        yield 'an unrelated class is not a mapping provider' => [\stdClass::class];
    }

    public function testMissingClassParametersKeepTheNormalContainerError(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('parameterized', (new Definition('%plugin.provider.class%'))->addTag('content_system.mapping_candidate_provider'));
        $this->expectExceptionObject(new ParameterNotFoundException('plugin.provider.class'));

        (new MappingCandidateProviderCompilerPass())->process($container);
    }

    public function testRejectsDuplicateRegisteredKeysForTheSameRootDuringContainerBuild(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('first', (new Definition(FirstRegisteredProvider::class))->addTag('content_system.mapping_candidate_provider'));
        $container->setDefinition('second', (new Definition(SecondRegisteredProvider::class))->addTag('content_system.mapping_candidate_provider'));

        $this->expectExceptionObject(DependencyInjectionException::mappingCandidateProviderKeyCollision(
            'product',
            'product.name',
            'first',
            'second',
        ));

        (new MappingCandidateProviderCompilerPass())->process($container);
    }

    public function testDetectsCollisionsBetweenWildcardAndRootSpecificProviders(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('wildcard', (new Definition(WildcardRegisteredProvider::class))->addTag('content_system.mapping_candidate_provider'));
        $container->setDefinition('root-specific', (new Definition(SecondRegisteredProvider::class))->addTag('content_system.mapping_candidate_provider'));

        $this->expectExceptionObject(DependencyInjectionException::mappingCandidateProviderKeyCollision(
            'product',
            'product.name',
            'wildcard',
            'root-specific',
        ));

        (new MappingCandidateProviderCompilerPass())->process($container);
    }

    public function testRejectsTwoWildcardProvidersDeclaringTheSameKey(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('first', (new Definition(WildcardRegisteredProvider::class))->addTag('content_system.mapping_candidate_provider'));
        $container->setDefinition('second', (new Definition(WildcardRegisteredProvider::class))->addTag('content_system.mapping_candidate_provider'));
        $this->expectExceptionObject(DependencyInjectionException::mappingCandidateProviderKeyCollision('*', 'product.name', 'first', 'second'));

        (new MappingCandidateProviderCompilerPass())->process($container);
    }

    public function testTheSameKeyCanBeDeclaredUnderDifferentRoots(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('first', (new Definition(FirstRegisteredProvider::class))->addTag('content_system.mapping_candidate_provider'));
        $container->setDefinition('other-root', (new Definition(SameKeyOtherRootRegisteredProvider::class))->addTag('content_system.mapping_candidate_provider'));
        $container->addCompilerPass(new MappingCandidateProviderCompilerPass());

        $container->compile();

        static::assertTrue($container->isCompiled());
    }

    public function testWildcardAndRootProvidersCanDeclareDifferentKeys(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('wildcard', (new Definition(WildcardRegisteredProvider::class))->addTag('content_system.mapping_candidate_provider'));
        $container->setDefinition('other-root', (new Definition(OtherRootRegisteredProvider::class))->addTag('content_system.mapping_candidate_provider'));
        $container->addCompilerPass(new MappingCandidateProviderCompilerPass());

        $container->compile();

        static::assertTrue($container->isCompiled());
    }

    public function testAllowsNonOverlappingProviderDeclarations(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('first', (new Definition(FirstRegisteredProvider::class))->addTag('content_system.mapping_candidate_provider'));
        $container->setDefinition('other-root', (new Definition(OtherRootRegisteredProvider::class))->addTag('content_system.mapping_candidate_provider'));

        (new MappingCandidateProviderCompilerPass())->process($container);

        static::assertCount(2, $container->findTaggedServiceIds('content_system.mapping_candidate_provider'));
    }

    public function testCoreProviderDeclarationsCompileTogether(): void
    {
        $container = new ContainerBuilder();
        foreach ([
            ProductMappingCandidateProvider::class,
            CategoryMappingCandidateProvider::class,
            LandingPageMappingCandidateProvider::class,
            CustomFieldMappingCandidateProvider::class,
        ] as $index => $providerClass) {
            $container->setDefinition(
                'provider.' . $index,
                (new Definition($providerClass))->addTag('content_system.mapping_candidate_provider'),
            );
        }

        (new MappingCandidateProviderCompilerPass())->process($container);

        static::assertCount(4, $container->findTaggedServiceIds('content_system.mapping_candidate_provider'));
    }
}

/**
 * @internal
 */
class FirstRegisteredProvider extends AbstractMappingCandidateProvider
{
    public static function registeredCandidateKeys(): array
    {
        return ['product' => ['product.name']];
    }

    public function supports(string $rootSource): bool
    {
        return false;
    }

    public function provide(string $rootSource): array
    {
        return [];
    }
}

/**
 * @internal
 */
class SecondRegisteredProvider extends FirstRegisteredProvider
{
}

/**
 * @internal
 */
class WildcardRegisteredProvider extends FirstRegisteredProvider
{
    public static function registeredCandidateKeys(): array
    {
        return ['*' => ['product.name']];
    }
}

/**
 * @internal
 */
class OtherRootRegisteredProvider extends FirstRegisteredProvider
{
    public static function registeredCandidateKeys(): array
    {
        return ['category' => ['category.name']];
    }
}

/**
 * @internal
 */
class SameKeyOtherRootRegisteredProvider extends FirstRegisteredProvider
{
    public static function registeredCandidateKeys(): array
    {
        return ['category' => ['product.name']];
    }
}
