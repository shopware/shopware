<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Layout\Field;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
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
 * End-to-end through the real DAL and the real style option registry: a valid style round-trips, an
 * empty style reads back empty, and a style violating the registry-derived constraints is rejected.
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

    #[TestDox('persists a valid style on an element and reads it back unchanged')]
    public function testPersistsAndReadsBackValidStyle(): void
    {
        $context = Context::createDefaultContext();
        $id = $this->ids->get('layout');
        $style = ['col-span' => ['md' => 6, 'lg' => 4], 'display' => ['xs' => false]];

        $this->repository()->create([$this->layout($id, $style)], $context);

        static::assertSame($style, $this->readElement($id, $context)->getStyle()->toArray());
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

        static::assertSame($style, $this->readElement($id, $context)->getStyle()->toArray());
    }

    #[TestDox('reads back an empty style for an element written without one')]
    public function testReadsBackEmptyStyleWhenAbsent(): void
    {
        $context = Context::createDefaultContext();
        $id = $this->ids->get('layout');

        $this->repository()->create([$this->layout($id, null)], $context);

        static::assertTrue($this->readElement($id, $context)->getStyle()->isEmpty());
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

    private function readElement(string $layoutId, Context $context): ContentElement
    {
        $layout = $this->repository()->search(new Criteria([$layoutId]), $context)->first();
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
