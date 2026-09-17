<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Mapping;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * Proves data mapping end-to-end on a listing page layout: an author maps the `text` property of a shipped
 * `Sw:Content:Text` element to `category.name`, and the Store API serves the bound category's own name in
 * place of the authored copy.
 *
 * Nothing in the rendering pipeline is mapping-aware. A mapping IS a root-scoped context consumer whose key
 * is a dotted path into the page-level data and whose `propertyAlias` names the property it fills, and the
 * delivered-context tier already outranks the authored tier at `RenderedElementFactory`. What the feature adds
 * on top is the declaration (`mappable: true` in the element type), the curated catalogue of offerable paths,
 * and the write gate that admits only a mapping satisfying both — so those are what the write-rejection cases
 * below pin, while the rendering case pins that the two halves meet.
 *
 * @internal
 */
#[Package('framework')]
#[Group('store-api')]
class CategoryLayoutDataMappingTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private const CATEGORY_NAME = 'Outdoor equipment';

    private const AUTHORED_TEXT = '<p>Authored copy</p>';

    private IdsCollection $ids;

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = new IdsCollection();
        $this->browser = $this->createSalesChannelBrowser();
    }

    #[TestDox('serves the bound category name in place of the authored copy when the text property is mapped')]
    public function testAMappedPropertyServesTheEntityValue(): void
    {
        $this->createCategory();
        $this->persistLayout($this->textElement(mappedTo: 'category.name'));

        static::assertSame(self::CATEGORY_NAME, $this->servedText());
    }

    /**
     * The control. Without it, a rendering bug that dropped `properties.text` entirely would still let the
     * mapped case pass by serving the entity value from some other tier.
     */
    #[TestDox('serves the authored copy when the same property carries no mapping')]
    public function testAnUnmappedPropertyServesItsAuthoredValue(): void
    {
        $this->createCategory();
        $this->persistLayout($this->textElement(mappedTo: null));

        static::assertSame(self::AUTHORED_TEXT, $this->servedText());
    }

    /**
     * The authored value is kept alongside the mapping rather than replaced by it, which is what lets the
     * Administration restore it when the author unmaps the property.
     */
    #[TestDox('keeps the authored value in storage while the mapping shadows it')]
    public function testMappingShadowsTheAuthoredValueWithoutErasingIt(): void
    {
        $this->createCategory();
        $this->persistLayout($this->textElement(mappedTo: 'category.name'));

        $stored = $this->rawStoredRoots()[0]['slots']['content'][0];

        static::assertSame(self::AUTHORED_TEXT, $stored['properties']['text']);
        static::assertSame('text', $stored['acceptsContext']['category.name']['propertyAlias']);
    }

    #[TestDox('rejects a mapping onto a path the category catalogue does not offer')]
    public function testTheWriteGateRejectsAnUncataloguedPath(): void
    {
        $this->createCategory();

        // A real, resolvable category member that the catalogue simply does not vouch for, so the rejection
        // can only come from the allowlist and not from the path failing to resolve.
        $codes = $this->assertLayoutRejected($this->textElement(mappedTo: 'category.cmsPageId'));

        static::assertSame([ContentSystemException::UNKNOWN_MAPPING_PATH], $codes);
    }

    /**
     * `category.media` is catalogued and resolves fine; it just yields a `MediaEntity` where the property
     * declares a string. Only the write gate can catch this — the render path would serve the entity.
     */
    #[TestDox('rejects a catalogued path whose value cannot fill the declared property type')]
    public function testTheWriteGateRejectsATypeMismatch(): void
    {
        $this->createCategory();

        $codes = $this->assertLayoutRejected($this->textElement(mappedTo: 'category.media'));

        static::assertSame([ContentSystemException::MAPPING_TYPE_MISMATCH], $codes);
    }

    /**
     * `Sw:Product:Listing` receives the category page's listing as a root-scoped consumer keyed by the bare
     * data-requirement name `productListing` and aliased onto its declared `listing` property — the shape
     * `Mutation/ContextConsumerMirror` writes for wiring it resolved, not a mapping an author made. The gate
     * once judged it and demanded `mappable: true` on `listing`, which made every listing page unsavable.
     */
    #[TestDox('saves a listing element whose root-scoped wiring is not a mapping')]
    public function testTheWriteGateAdmitsMirroredRootScopedWiring(): void
    {
        $this->createCategory();

        $this->persistLayout([
            'id' => $this->ids->create('listing'),
            'component' => 'Sw:Product:Listing',
            'properties' => [],
            'acceptsContext' => [
                'productListing' => [
                    'type' => 'single',
                    'required' => true,
                    'propertyAlias' => 'listing',
                    'scope' => 'root',
                ],
            ],
        ]);

        $stored = $this->rawStoredRoots()[0]['slots']['content'][0];

        static::assertSame('listing', $stored['acceptsContext']['productListing']['propertyAlias']);
    }

    /**
     * The `text` property of the shipped text element, optionally carrying a mapping.
     *
     * @return array<string, mixed>
     */
    private function textElement(?string $mappedTo): array
    {
        $element = [
            'id' => $this->ids->create('text'),
            'component' => 'Sw:Content:Text',
            'properties' => ['text' => self::AUTHORED_TEXT],
        ];

        if ($mappedTo !== null) {
            $element['acceptsContext'] = [
                $mappedTo => [
                    'type' => 'single',
                    'required' => false,
                    'propertyAlias' => 'text',
                    'scope' => 'root',
                ],
            ];
        }

        return $element;
    }

    /**
     * The served `properties.text` of the fixture's text element.
     */
    private function servedText(): string
    {
        $this->browser->request('GET', '/store-api/content/category/' . $this->ids->get('category'));

        $response = $this->browser->getResponse();
        $content = (string) $response->getContent();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);

        $body = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($body);

        $text = $body['elements'][0]['slots']['content'][0]['properties']['text'] ?? null;
        static::assertIsString($text, 'The fixture text element must be served with a text property.');

        return $text;
    }

    /**
     * Writes the layout, expects the write gate to reject it, and returns the codes it raised.
     *
     * @param array<string, mixed> $element
     *
     * @return list<string>
     */
    private function assertLayoutRejected(array $element): array
    {
        try {
            $this->persistLayout($element);
        } catch (WriteException $exception) {
            $codes = [];

            foreach ($exception->getExceptions() as $inner) {
                if (!$inner instanceof WriteConstraintViolationException) {
                    continue;
                }

                foreach ($inner->getViolations() as $violation) {
                    $codes[] = (string) $violation->getCode();
                }
            }

            return $codes;
        }

        static::fail('Expected the content layout write gate to reject the mapping.');
    }

    private function createCategory(): void
    {
        $this->repository('category.repository')->create([[
            'id' => $this->ids->create('category'),
            'name' => self::CATEGORY_NAME,
            'active' => true,
        ]], Context::createDefaultContext());
    }

    /**
     * One grid root holding the given element, assigned to the fixture category. The root is a container
     * because the shipped text element is authored inside one; the mapping under test sits on the child.
     *
     * @param array<string, mixed> $element
     */
    private function persistLayout(array $element): void
    {
        $context = Context::createDefaultContext();

        $this->repository('content_layout.repository')->create([[
            'id' => $this->ids->create('layout'),
            'name' => 'category-data-mapping',
            'version' => '1.0.0',
            'rootSource' => 'category',
            'layout' => [[
                'id' => $this->ids->create('root-grid'),
                'component' => 'Sw:Grid:Container',
                'properties' => [],
                'slots' => ['content' => [$element]],
            ]],
        ]], $context);

        $this->repository('category_content_layout.repository')->create([[
            'id' => $this->ids->create('assignment'),
            'categoryId' => $this->ids->get('category'),
            'salesChannelId' => null,
            'contentLayoutId' => $this->ids->get('layout'),
        ]], $context);
    }

    /**
     * The persisted `layout` column, read raw rather than through the DAL, so the assertion over it speaks
     * about what was stored rather than about what a decode would reconstruct.
     *
     * @return list<array<string, mixed>>
     */
    private function rawStoredRoots(): array
    {
        $connection = static::getContainer()->get(Connection::class);
        static::assertInstanceOf(Connection::class, $connection);

        $raw = $connection->fetchOne(
            'SELECT `layout` FROM `content_layout` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($this->ids->get('layout'))]
        );
        static::assertIsString($raw);

        $decoded = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);

        return array_values($decoded);
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function repository(string $serviceId): EntityRepository
    {
        $repository = static::getContainer()->get($serviceId);
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}
