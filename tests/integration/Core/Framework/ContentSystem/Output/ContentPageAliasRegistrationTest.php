<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Output;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Output\Encoder\ContentDataPageEncoder;
use Shopware\Core\Framework\ContentSystem\Output\Encoder\ContentDecomposedPageEncoder;
use Shopware\Core\Framework\ContentSystem\Output\Encoder\ContentPageEncoder;
use Shopware\Core\Framework\ContentSystem\Output\Struct\ContentSkeletonElement;
use Shopware\Core\Framework\ContentSystem\Output\Struct\ContentSkeletonPage;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\Entity\DefinitionRegistryChain;
use Shopware\Tests\Integration\Core\Framework\ContentSystem\SalesChannel\ContentRouteRenderingTest;

/**
 * Pins the one constraint that makes `Output/Struct/EncodedContentPage` safe: a content page alias resolves to
 * NO registered entity definition, so `StructEncoder`'s protection gate short-circuits on it instead of judging
 * a body's keys against some entity's fields. An entity registered under one of these names would make the gate
 * strip every content key it cannot find a field for, and would do it silently.
 *
 * `DefinitionRegistryChain` is the registry under test rather than a convenient stand-in: it is the exact
 * collaborator `StructEncoder::isProtected()` asks, and it answers for the core and the sales-channel registry
 * together.
 *
 * Each alias is read from its own source of truth rather than repeated as a literal, so a rename re-points the
 * pin at the new name. The wire strings themselves are pinned separately, by the per-format `apiAlias`
 * assertions in
 * {@see ContentRouteRenderingTest}.
 *
 * @internal
 */
#[Package('framework')]
class ContentPageAliasRegistrationTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @return \Generator<string, array{string}>
     */
    public static function contentPageAliasProvider(): \Generator
    {
        yield 'full format page alias' => [ContentPageEncoder::PAGE_API_ALIAS];
        yield 'decomposed format page alias' => [ContentDecomposedPageEncoder::PAGE_API_ALIAS];
        yield 'data format page alias' => [ContentDataPageEncoder::PAGE_API_ALIAS];
        yield 'skeleton format page alias' => [(new ContentSkeletonPage('', [], '', null))->getApiAlias()];
        yield 'skeleton element alias' => [ContentSkeletonElement::fromRendered([new RenderedElement('id', 'component')])[0]->getApiAlias()];
    }

    #[DataProvider('contentPageAliasProvider')]
    #[TestDox('registers no entity definition under a content page alias')]
    public function testNoEntityIsRegisteredUnderAContentPageAlias(string $alias): void
    {
        static::assertFalse(
            $this->definitionRegistry()->has($alias),
            'An entity registered as "' . $alias . '" would put the store-api protection gate in charge of a '
            . 'content body: it would judge the body keys against that entity\'s fields and strip the ones it '
            . 'does not find. Rename the entity, or stop carrying content bodies under this alias.',
        );
    }

    #[TestDox('answers true for an entity name that really is registered, so the negative cases are not vacuous')]
    public function testTheRegistryResolvesARegisteredEntityName(): void
    {
        static::assertTrue($this->definitionRegistry()->has('media'));
    }

    private function definitionRegistry(): DefinitionRegistryChain
    {
        $registry = static::getContainer()->get(DefinitionRegistryChain::class);
        static::assertInstanceOf(DefinitionRegistryChain::class, $registry);

        return $registry;
    }
}
