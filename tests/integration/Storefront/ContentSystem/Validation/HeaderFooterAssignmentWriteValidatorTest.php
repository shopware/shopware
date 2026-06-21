<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\ContentSystem\Validation;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
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

    #[TestDox('persists a header assignment when the bound layout is resolvable without root context')]
    public function testAcceptsResolvableLayoutBoundToHeader(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = $this->createLayout(TestElementTypeLoader::RESOLVABLE, $context);

        $assignmentId = Uuid::randomHex();
        $this->headerRepository()->create([['id' => $assignmentId, 'contentLayoutId' => $layoutId]], $context);

        static::assertSame($assignmentId, $this->headerRepository()->searchIds(new Criteria([$assignmentId]), $context)->firstId());
    }

    #[TestDox('rejects a header assignment when the bound layout needs root-ambient context')]
    public function testRejectsUnresolvableLayoutBoundToHeader(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = $this->createLayout(TestElementTypeLoader::UNRESOLVABLE, $context);

        $assignmentId = Uuid::randomHex();

        try {
            $this->headerRepository()->create([['id' => $assignmentId, 'contentLayoutId' => $layoutId]], $context);
            static::fail('Expected the header binding gate to reject the unresolvable layout.');
        } catch (WriteException $exception) {
            static::assertStringContainsString('Required property "target" is not deterministically resolvable', $exception->getMessage());
        }

        static::assertNull($this->headerRepository()->searchIds(new Criteria([$assignmentId]), $context)->firstId());
    }

    #[TestDox('rejects a footer assignment when the bound layout needs root-ambient context')]
    public function testRejectsUnresolvableLayoutBoundToFooter(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = $this->createLayout(TestElementTypeLoader::UNRESOLVABLE, $context);

        $assignmentId = Uuid::randomHex();

        try {
            $this->footerRepository()->create([['id' => $assignmentId, 'contentLayoutId' => $layoutId]], $context);
            static::fail('Expected the footer binding gate to reject the unresolvable layout.');
        } catch (WriteException $exception) {
            static::assertStringContainsString('Required property "target" is not deterministically resolvable', $exception->getMessage());
        }

        static::assertNull($this->footerRepository()->searchIds(new Criteria([$assignmentId]), $context)->firstId());
    }

    #[TestDox('rejects an edit that makes a header-bound layout unresolvable')]
    public function testRejectsEditBreakingResolvabilityForBoundHeader(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = $this->createLayout(TestElementTypeLoader::RESOLVABLE, $context);
        $this->headerRepository()->create([['id' => Uuid::randomHex(), 'contentLayoutId' => $layoutId]], $context);

        try {
            $this->layoutRepository()->update([['id' => $layoutId, 'layout' => $this->tree(TestElementTypeLoader::UNRESOLVABLE)]], $context);
            static::fail('Expected the bound-header re-check to reject the breaking edit.');
        } catch (WriteException $exception) {
            static::assertStringContainsString('Required property "target" is not deterministically resolvable', $exception->getMessage());
        }
    }

    #[TestDox('rejects a single batch that creates a layout and binds it to a header at once when the layout is unresolvable')]
    public function testRejectsAtomicCreateAndBindOfUnresolvableHeaderLayout(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = Uuid::randomHex();
        $assignmentId = Uuid::randomHex();

        try {
            $this->layoutRepository()->create([$this->layoutWithHeaderBinding($layoutId, $assignmentId, TestElementTypeLoader::UNRESOLVABLE)], $context);
            static::fail('Expected the header binding gate to reject the atomic create-and-bind of an unresolvable layout.');
        } catch (WriteException $exception) {
            static::assertStringContainsString('Required property "target" is not deterministically resolvable', $exception->getMessage());
        }

        static::assertNull($this->layoutRepository()->searchIds(new Criteria([$layoutId]), $context)->firstId());
        static::assertNull($this->headerRepository()->searchIds(new Criteria([$assignmentId]), $context)->firstId());
    }

    #[TestDox('persists a single batch that creates a layout and binds it to a header at once when the layout is resolvable')]
    public function testAcceptsAtomicCreateAndBindOfResolvableHeaderLayout(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = Uuid::randomHex();
        $assignmentId = Uuid::randomHex();

        $this->layoutRepository()->create([$this->layoutWithHeaderBinding($layoutId, $assignmentId, TestElementTypeLoader::RESOLVABLE)], $context);

        static::assertSame($layoutId, $this->layoutRepository()->searchIds(new Criteria([$layoutId]), $context)->firstId());
        static::assertSame($assignmentId, $this->headerRepository()->searchIds(new Criteria([$assignmentId]), $context)->firstId());
    }

    private function createLayout(string $component, Context $context): string
    {
        $id = Uuid::randomHex();
        $this->layoutRepository()->create([[
            'id' => $id,
            'name' => 'header-footer-gate-layout',
            'version' => '1.0.0',
            'layout' => $this->tree($component),
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
     * land in a single write batch (one PreWriteValidationEvent) — the atomic create-and-bind path. A header
     * exposes no root-ambient context, so the bound layout must resolve without any page data.
     *
     * @return array<string, mixed>
     */
    private function layoutWithHeaderBinding(string $layoutId, string $assignmentId, string $component): array
    {
        return [
            'id' => $layoutId,
            'name' => 'atomic-create-and-bind-header-layout',
            'version' => '1.0.0',
            'layout' => $this->tree($component),
            'headerContentLayouts' => [
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
