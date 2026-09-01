<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Layout\Field;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\Stub\ContentSystem\TestElementTypeLoader;
use Shopware\Core\Test\Stub\ContentSystem\TestStyleOptionLoader;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * End-to-end through the real DAL and the real style option registry: a valid style round-trips in the
 * canonical form the write boundary stores, an empty style reads back empty, and a style violating the
 * registry-derived constraints is rejected.
 *
 * @internal
 */
#[Package('framework')]
class ElementStylePersistenceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ids = new IdsCollection();
    }

    #[TestDox('persists a valid style on an element and reads it back in the write-boundary canonical form')]
    public function testPersistsAndReadsBackValidStyle(): void
    {
        $context = Context::createDefaultContext();
        $id = $this->ids->get('layout');
        // `display` declares a default, so the write boundary fills its unspecified breakpoints from it;
        // `col-span` declares none, so its partial map is stored exactly as authored.
        $style = ['col-span' => ['md' => 6, 'lg' => 4], 'display' => ['xs' => false]];
        $expected = [
            'col-span' => ['md' => 6, 'lg' => 4],
            'display' => ['xs' => false, 'sm' => true, 'md' => true, 'lg' => true, 'xl' => true, 'xxl' => true],
        ];

        $this->repository()->create([$this->layout($id, $style)], $context);

        static::assertSame(
            $this->byKey($expected),
            $this->byKey($this->readElement($id, $context)->style->toArray()),
        );
    }

    #[TestDox('persists a flat option as a bare scalar beside a breakpoint-aware option and reads both back unchanged')]
    public function testPersistsAndReadsBackFlatStyleOption(): void
    {
        $context = Context::createDefaultContext();
        $id = $this->ids->get('layout');
        // The flat option stores a scalar; the breakpoint-aware option a per-breakpoint map. Both shapes
        // coexist in one element and must survive write -> JSON column -> registry-free decode unchanged.
        $style = [TestStyleOptionLoader::FLAT_INTEGER => 10, 'col-span' => ['md' => 6]];

        $this->repository()->create([$this->layout($id, $style)], $context);

        static::assertSame(
            $this->byKey($style),
            $this->byKey($this->readElement($id, $context)->style->toArray()),
        );
    }

    #[TestDox('reads back an empty style for an element written without one')]
    public function testReadsBackEmptyStyleWhenAbsent(): void
    {
        $context = Context::createDefaultContext();
        $id = $this->ids->get('layout');

        $this->repository()->create([$this->layout($id, null)], $context);

        static::assertTrue($this->readElement($id, $context)->style->isEmpty());
    }

    #[TestDox('rejects a write whose style references an option not in the registry')]
    public function testRejectsUnknownStyleOption(): void
    {
        $context = Context::createDefaultContext();

        try {
            $this->repository()->create([$this->layout(null, ['not-a-real-option' => ['md' => 1]])], $context);
            static::fail('Expected the field serializer to reject the unknown style option.');
        } catch (WriteException $exception) {
            static::assertStringContainsString('not-a-real-option', $exception->getMessage());
        }
    }

    #[TestDox('rejects a write whose style value falls outside the declared range')]
    public function testRejectsValueOutsideDeclaredRange(): void
    {
        $context = Context::createDefaultContext();

        try {
            $this->repository()->create([$this->layout(null, ['col-span' => ['md' => 99]])], $context);
            static::fail('Expected the field serializer to reject the out-of-range span value.');
        } catch (WriteException $exception) {
            static::assertStringContainsString('col-span', $exception->getMessage());
            // The range-specific phrasing distinguishes a Range violation from a Type mismatch on the same field
            static::assertStringContainsString('between 1 and 12', $exception->getMessage());
        }
    }

    #[TestDox('rejects a write whose style carries a malformed shape and stores no row')]
    public function testRejectsMalformedStyleShapeAndStoresNoRow(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = $this->ids->get('layout');

        try {
            // The decode gate runs before the constraint pass judges the tree; without the throw the write
            // would succeed with the unknown breakpoint silently stripped.
            $this->repository()->create([$this->layout($layoutId, ['col-span' => ['bogus-breakpoint' => 6]])], $context);
            static::fail('Expected the decode gate to reject the unknown breakpoint key.');
        } catch (WriteException $exception) {
            static::assertSame(
                ContentSystemException::INVALID_MAP_KEY,
                iterator_to_array($exception->getErrors(), false)[0]['code'],
            );
        }

        static::assertNull($this->repository()->searchIds(new Criteria([$layoutId]), $context)->firstId());
    }

    /**
     * Key order in a stored JSON map is not part of the contract, and the two engines disagree about it:
     * MySQL normalises object members by key length then bytewise, MariaDB preserves insertion order. So a
     * `['md' => …, 'lg' => …]` map read back byte-for-byte is green locally and red on CI. Sorting both sides
     * by key keeps the comparison on decoded structure while leaving values, types and list order strict.
     *
     * @param array<string, mixed> $style
     *
     * @return array<string, mixed>
     */
    private function byKey(array $style): array
    {
        ksort($style);

        foreach ($style as $option => $value) {
            if (!\is_array($value)) {
                continue;
            }

            ksort($value);
            $style[$option] = $value;
        }

        return $style;
    }

    private function readElement(string $layoutId, Context $context): StoredElement
    {
        $layout = $this->repository()->search(new Criteria([$layoutId]), $context)->getEntities()->first();
        static::assertInstanceOf(ContentLayoutEntity::class, $layout);

        $elements = $layout->getLayout();
        static::assertArrayHasKey(0, $elements);

        return $elements[0];
    }

    /**
     * @param array<string, string|int|float|bool|array<string, string|int|float|bool>>|null $style
     *
     * @return array<string, mixed>
     */
    private function layout(?string $id, ?array $style): array
    {
        $element = ['id' => $this->ids->get('element'), 'component' => TestElementTypeLoader::RESOLVABLE, 'properties' => []];

        if ($style !== null) {
            $element['style'] = $style;
        }

        return [
            'id' => $id ?? $this->ids->get('layout'),
            'name' => 'style-persistence-test',
            'version' => '1.0.0',
            'rootSource' => 'category',
            'layout' => [$element],
        ];
    }

    /**
     * @return EntityRepository<ContentLayoutCollection>
     */
    private function repository(): EntityRepository
    {
        $repository = $this->getContainer()->get('content_layout.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}
