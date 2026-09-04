<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Storefront;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pins that a persisted `content_layout` renders to Storefront markup through the real
 * `frontend.content.layout` route, and that a media-backed element's media URL survives the
 * `|sw_encode_media_url` filter chain into the emitted `img` tag.
 *
 * The URL expectation is taken from the media entity's own `url` (derived from the persisted `path`) rather
 * than from `UrlEncodingTwigFilter::encodeMediaUrl()`, so the assertion does not restate the filter the
 * template itself calls. The attribute assertions address the one `img` node carrying the element id, so an
 * attribute that moved onto a wrapper element is a failure rather than a surviving substring.
 *
 * Only the attributes the components declare (`src`, `height`, `loading`) plus the `data-element-id` the
 * element loop adds are asserted. Undeclared element properties currently fall through to HTML attributes;
 * that fall-through is not pinned, so no markup-equality assertion is made.
 *
 * @internal
 */
#[Package('framework')]
class ContentLayoutStorefrontRenderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private const MEDIA_FILE_NAME = 'storefront-render-probe';

    private const MEDIA_PATH = 'media/storefront-render-probe.png';

    private const IMAGE_HEIGHT = '240';

    private const IMAGE_LOADING = 'eager';

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = new IdsCollection();
        $this->createCategory();
        $this->createMedia();
        $this->persistLayout();
    }

    #[TestDox('renders every layout element with its data-element-id into the storefront markup')]
    public function testElementsCarryTheirElementIdIntoTheMarkup(): void
    {
        $html = $this->renderContentLayout();

        static::assertStringContainsString('data-element-id="' . $this->ids->get('root-grid') . '"', $html);
        static::assertStringContainsString('data-element-id="' . $this->ids->get('image') . '"', $html);
    }

    #[TestDox('renders the media url of a media-backed element into the img src attribute')]
    public function testEncodedMediaUrlReachesTheRenderedImageTag(): void
    {
        $media = $this->loadMedia();

        // The fixture really is the media-backed one this test claims, and the expectation is read off the
        // entity rather than off the filter the template calls, so a wrong-but-non-empty URL cannot agree
        // with itself on both sides.
        static::assertSame(self::MEDIA_PATH, $media->getPath());

        $url = $media->getUrl();
        static::assertNotSame('', $url);
        // The generated URL carries a cache-busting query, so the persisted path is a substring, not a suffix.
        static::assertStringContainsString('/' . self::MEDIA_PATH, $url);

        static::assertSame($url, $this->renderedImageTag()->getAttribute('src'));
    }

    #[TestDox('renders the declared image attributes onto the img tag of the image element')]
    public function testDeclaredImageAttributesAreRendered(): void
    {
        $image = $this->renderedImageTag();

        static::assertSame(self::IMAGE_HEIGHT, $image->getAttribute('height'));
        static::assertSame(self::IMAGE_LOADING, $image->getAttribute('loading'));
    }

    /**
     * The one `img` node carrying the image element's `data-element-id`. Addressing the node instead of the
     * markup string is what makes an attribute that moved to a wrapper element a failure.
     */
    private function renderedImageTag(): \DOMElement
    {
        $document = new \DOMDocument();

        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML('<?xml encoding="utf-8" ?>' . $this->renderContentLayout());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        static::assertTrue($loaded);

        $nodes = (new \DOMXPath($document))->query(
            \sprintf('//img[@data-element-id="%s"]', $this->ids->get('image'))
        );

        static::assertInstanceOf(\DOMNodeList::class, $nodes);
        static::assertCount(1, $nodes, 'Exactly one img element must carry the image element id.');

        $image = $nodes->item(0);
        static::assertInstanceOf(\DOMElement::class, $image);

        return $image;
    }

    private function renderContentLayout(): string
    {
        $response = $this->request('GET', 'content/category/' . $this->ids->get('category'), []);

        $html = (string) $response->getContent();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $html);

        return $html;
    }

    private function persistLayout(): void
    {
        $context = Context::createDefaultContext();

        $this->repository('content_layout.repository')->create([[
            'id' => $this->ids->get('layout'),
            'name' => 'storefront-render',
            'version' => '1.0.0',
            'rootSource' => 'category',
            'layout' => [[
                'id' => $this->ids->get('root-grid'),
                'component' => 'Sw:Grid:Container',
                'properties' => [],
                'slots' => [
                    'content' => [[
                        'id' => $this->ids->get('image'),
                        'component' => 'Sw:Media:Image',
                        'properties' => [
                            'mediaId' => $this->ids->get('media'),
                            'height' => self::IMAGE_HEIGHT,
                            'loading' => self::IMAGE_LOADING,
                        ],
                        'dataRequirements' => [
                            'media' => ['source' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']],
                        ],
                    ]],
                ],
            ]],
        ]], $context);

        $this->repository('category_content_layout.repository')->create([[
            'id' => $this->ids->get('assignment'),
            'categoryId' => $this->ids->get('category'),
            'salesChannelId' => null,
            'contentLayoutId' => $this->ids->get('layout'),
        ]], $context);
    }

    private function createCategory(): void
    {
        $this->repository('category.repository')->create([[
            'id' => $this->ids->create('category'),
            'name' => 'Storefront render category',
            'active' => true,
        ]], Context::createDefaultContext());
    }

    private function createMedia(): void
    {
        $this->repository('media.repository')->create([[
            'id' => $this->ids->create('media'),
            'fileName' => self::MEDIA_FILE_NAME,
            'fileExtension' => 'png',
            'mimeType' => 'image/png',
            'path' => self::MEDIA_PATH,
            'private' => false,
        ]], Context::createDefaultContext());
    }

    private function loadMedia(): MediaEntity
    {
        $media = $this->repository('media.repository')
            ->search(new Criteria([$this->ids->get('media')]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(MediaEntity::class, $media);

        return $media;
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
