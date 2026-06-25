<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\ContentSystem\Validation;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutGate;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\ContentSystem\TestElementTypeLoader;

/**
 * @internal
 */
#[Package('framework')]
class HeaderFooterAssignmentWriteValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    #[TestDox('persists a header assignment when the layout root source is header')]
    public function testAcceptsHeaderAssignmentMatchingRootSource(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = $this->createLayout('header', $context);

        $assignmentId = Uuid::randomHex();
        $this->headerRepository()->create([['id' => $assignmentId, 'contentLayoutId' => $layoutId]], $context);

        static::assertSame($assignmentId, $this->headerRepository()->searchIds(new Criteria([$assignmentId]), $context)->firstId());
    }

    #[TestDox('rejects a header assignment when the layout was created for a different root source')]
    public function testRejectsHeaderAssignmentMismatchingRootSource(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = $this->createLayout('footer', $context);

        $assignmentId = Uuid::randomHex();

        try {
            $this->headerRepository()->create([['id' => $assignmentId, 'contentLayoutId' => $layoutId]], $context);
            static::fail('Expected the type-match to reject a footer-rooted layout bound to a header.');
        } catch (WriteException $exception) {
            static::assertStringContainsString('root source is "footer"', $exception->getMessage());
            static::assertSame(ContentSystemException::ROOT_SOURCE_ASSIGNMENT_MISMATCH, iterator_to_array($exception->getErrors(), false)[0]['code']);
        }

        static::assertNull($this->headerRepository()->searchIds(new Criteria([$assignmentId]), $context)->firstId());
    }

    #[TestDox('persists a footer assignment when the layout root source is footer')]
    public function testAcceptsFooterAssignmentMatchingRootSource(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = $this->createLayout('footer', $context);

        $assignmentId = Uuid::randomHex();
        $this->footerRepository()->create([['id' => $assignmentId, 'contentLayoutId' => $layoutId]], $context);

        static::assertSame($assignmentId, $this->footerRepository()->searchIds(new Criteria([$assignmentId]), $context)->firstId());
    }

    #[TestDox('rejects a footer assignment when the layout was created for a different root source')]
    public function testRejectsFooterAssignmentMismatchingRootSource(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = $this->createLayout('header', $context);

        $assignmentId = Uuid::randomHex();

        try {
            $this->footerRepository()->create([['id' => $assignmentId, 'contentLayoutId' => $layoutId]], $context);
            static::fail('Expected the type-match to reject a header-rooted layout bound to a footer.');
        } catch (WriteException $exception) {
            static::assertStringContainsString('root source is "header"', $exception->getMessage());
        }

        static::assertNull($this->footerRepository()->searchIds(new Criteria([$assignmentId]), $context)->firstId());
    }

    #[TestDox('bypasses the type-match when the write context carries the skip flag')]
    public function testSkipFlagBypassesTypeMatch(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = $this->createLayout('footer', $context);

        $skipContext = Context::createDefaultContext();
        $skipContext->addState(LayoutGate::SKIP_VALIDATION_STATE);
        $assignmentId = Uuid::randomHex();

        $this->headerRepository()->create([['id' => $assignmentId, 'contentLayoutId' => $layoutId]], $skipContext);

        static::assertSame($assignmentId, $this->headerRepository()->searchIds(new Criteria([$assignmentId]), $context)->firstId());
    }

    #[TestDox('persists a single batch that creates a header-rooted layout and binds it to a header at once')]
    public function testAcceptsAtomicCreateAndBindMatchingHeader(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = Uuid::randomHex();
        $assignmentId = Uuid::randomHex();

        $this->layoutRepository()->create([$this->layoutWithHeaderBinding($layoutId, $assignmentId, 'header')], $context);

        static::assertSame($layoutId, $this->layoutRepository()->searchIds(new Criteria([$layoutId]), $context)->firstId());
        static::assertSame($assignmentId, $this->headerRepository()->searchIds(new Criteria([$assignmentId]), $context)->firstId());
    }

    #[TestDox('rejects an atomic create-and-bind to a header whose in-flight root source is not header')]
    public function testRejectsAtomicCreateAndBindMismatchingHeader(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = Uuid::randomHex();
        $assignmentId = Uuid::randomHex();

        try {
            $this->layoutRepository()->create([$this->layoutWithHeaderBinding($layoutId, $assignmentId, 'footer')], $context);
            static::fail('Expected the type-match to read the in-flight root source and reject the create-and-bind.');
        } catch (WriteException $exception) {
            static::assertStringContainsString('root source is "footer"', $exception->getMessage());
        }

        static::assertNull($this->layoutRepository()->searchIds(new Criteria([$layoutId]), $context)->firstId());
        static::assertNull($this->headerRepository()->searchIds(new Criteria([$assignmentId]), $context)->firstId());
    }

    #[TestDox('persists a single batch that creates a footer-rooted layout and binds it to a footer at once')]
    public function testAcceptsAtomicCreateAndBindMatchingFooter(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = Uuid::randomHex();
        $assignmentId = Uuid::randomHex();

        $this->layoutRepository()->create([$this->layoutWithFooterBinding($layoutId, $assignmentId, 'footer')], $context);

        static::assertSame($layoutId, $this->layoutRepository()->searchIds(new Criteria([$layoutId]), $context)->firstId());
        static::assertSame($assignmentId, $this->footerRepository()->searchIds(new Criteria([$assignmentId]), $context)->firstId());
    }

    #[TestDox('rejects an atomic create-and-bind to a footer whose in-flight root source is not footer')]
    public function testRejectsAtomicCreateAndBindMismatchingFooter(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = Uuid::randomHex();
        $assignmentId = Uuid::randomHex();

        try {
            $this->layoutRepository()->create([$this->layoutWithFooterBinding($layoutId, $assignmentId, 'header')], $context);
            static::fail('Expected the type-match to read the in-flight root source and reject the create-and-bind.');
        } catch (WriteException $exception) {
            static::assertStringContainsString('root source is "header"', $exception->getMessage());
        }

        static::assertNull($this->layoutRepository()->searchIds(new Criteria([$layoutId]), $context)->firstId());
        static::assertNull($this->footerRepository()->searchIds(new Criteria([$assignmentId]), $context)->firstId());
    }

    private function createLayout(string $rootSource, Context $context): string
    {
        $id = Uuid::randomHex();
        $this->layoutRepository()->create([[
            'id' => $id,
            'name' => 'header-footer-gate-layout',
            'version' => '1.0.0',
            'rootSource' => $rootSource,
            'layout' => $this->tree(TestElementTypeLoader::RESOLVABLE),
        ]], $context);

        return $id;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tree(string $component): array
    {
        return [
            ['id' => Uuid::randomHex(), 'component' => $component, 'properties' => []],
        ];
    }

    /**
     * A layout payload that nests its header assignment, so the layout INSERT and the header_content_layout INSERT
     * land in a single write batch (one PreWriteValidationEvent) — the atomic create-and-bind path. The type-match
     * reads the layout's in-flight root source rather than the (not-yet-committed) row.
     *
     * @return array<string, mixed>
     */
    private function layoutWithHeaderBinding(string $layoutId, string $assignmentId, string $rootSource): array
    {
        return [
            'id' => $layoutId,
            'name' => 'atomic-create-and-bind-header-layout',
            'version' => '1.0.0',
            'rootSource' => $rootSource,
            'layout' => $this->tree(TestElementTypeLoader::RESOLVABLE),
            'headerContentLayouts' => [
                ['id' => $assignmentId],
            ],
        ];
    }

    /**
     * The footer counterpart to {@see layoutWithHeaderBinding}: nests the footer assignment so the layout INSERT and
     * the footer_content_layout INSERT land in a single write batch, exercising the atomic create-and-bind path
     * against the layout's in-flight root source.
     *
     * @return array<string, mixed>
     */
    private function layoutWithFooterBinding(string $layoutId, string $assignmentId, string $rootSource): array
    {
        return [
            'id' => $layoutId,
            'name' => 'atomic-create-and-bind-footer-layout',
            'version' => '1.0.0',
            'rootSource' => $rootSource,
            'layout' => $this->tree(TestElementTypeLoader::RESOLVABLE),
            'footerContentLayouts' => [
                ['id' => $assignmentId],
            ],
        ];
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function layoutRepository(): EntityRepository
    {
        $repository = $this->getContainer()->get('content_layout.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function headerRepository(): EntityRepository
    {
        $repository = $this->getContainer()->get('header_content_layout.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function footerRepository(): EntityRepository
    {
        $repository = $this->getContainer()->get('footer_content_layout.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}
