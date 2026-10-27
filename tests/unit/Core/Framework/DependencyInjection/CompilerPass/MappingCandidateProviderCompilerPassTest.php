<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
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

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MappingCandidateProviderCompilerPass::class)]
class MappingCandidateProviderCompilerPassTest extends TestCase
{
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
