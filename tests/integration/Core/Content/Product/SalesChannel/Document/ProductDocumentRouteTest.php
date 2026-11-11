<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\SalesChannel\Document;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Package('inventory')]
#[Group('store-api')]
class ProductDocumentRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM], ['id' => $this->getDeDeLanguageId()]],
        ]);

        $this->createData();
    }

    public function testLoadReturnsTheDocumentsWithAPublicFile(): void
    {
        $response = $this->fetch('drill');

        static::assertSame(1, $response['total']);
        static::assertSame(['Manual'], array_column(array_column($response['elements'], 'translated'), 'title'));
        static::assertSame($this->ids->get('manual'), $response['elements'][0]['media']['id']);
    }

    public function testLoadCountsOnlyTheDocumentsWithAPublicFileAcrossPages(): void
    {
        $this->browser->request('POST', '/store-api/product/' . $this->ids->get('drill') . '/documents', ['limit' => 1, 'page' => 1, 'total-count-mode' => 'exact']);

        $response = $this->decode();
        static::assertSame(1, $response['total']);
        static::assertSame([$this->ids->get('manual-document')], array_column($response['elements'], 'id'));
    }

    public function testLoadLeavesOutPrivateFilesOfOtherLanguagesInTheTranslations(): void
    {
        $this->browser->request('POST', '/store-api/product/' . $this->ids->get('saw') . '/documents', [
            'associations' => ['translations' => ['associations' => ['media' => []]]],
        ]);

        $response = $this->decode();
        static::assertSame(1, $response['total']);

        $translations = $response['elements'][0]['translations'];
        static::assertSame([$this->ids->get('datasheet')], array_column(array_column($translations, 'media'), 'id'));
        static::assertStringNotContainsString($this->ids->get('datenblatt'), (string) $this->browser->getResponse()->getContent());
    }

    public function testLoadReturnsTheDocumentsAVariantInheritsFromItsParent(): void
    {
        $response = $this->fetch('drill-red');

        static::assertSame([$this->ids->get('manual-document')], array_column($response['elements'], 'id'));
    }

    public function testLoadReturnsTheOwnDocumentsOfAVariant(): void
    {
        $response = $this->fetch('drill-blue');

        static::assertSame(['Blue sheet'], array_column(array_column($response['elements'], 'translated'), 'title'));
        static::assertSame($this->ids->get('blue-sheet'), $response['elements'][0]['media']['id']);
    }

    public function testLoadResolvesTheTitleAndFileOfTheRequestedLanguage(): void
    {
        $this->browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_LANGUAGE_ID, $this->getDeDeLanguageId());

        $response = $this->fetch('drill');

        static::assertSame(['Handbuch'], array_column(array_column($response['elements'], 'translated'), 'title'));
        static::assertSame($this->ids->get('handbuch'), $response['elements'][0]['media']['id']);
    }

    public function testLoadSupportsGetRequests(): void
    {
        $this->browser->request('GET', '/store-api/product/' . $this->ids->get('drill') . '/documents');

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame(1, $this->decode()['total']);
    }

    public function testLoadReturnsNotFoundForAProductThatIsNotVisible(): void
    {
        $this->browser->request('POST', '/store-api/product/' . $this->ids->get('hidden') . '/documents');

        static::assertSame(404, $this->browser->getResponse()->getStatusCode());
        static::assertSame('CONTENT__PRODUCT_NOT_FOUND', $this->decode()['errors'][0]['code']);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetch(string $product): array
    {
        $this->browser->request('POST', '/store-api/product/' . $this->ids->get($product) . '/documents');

        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());

        return $this->decode();
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(): array
    {
        return json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    private function createData(): void
    {
        $context = Context::createDefaultContext();

        static::getContainer()->get('media.repository')->create([
            ['id' => $this->ids->create('manual'), 'private' => false],
            ['id' => $this->ids->create('handbuch'), 'private' => false],
            ['id' => $this->ids->create('certificate'), 'private' => false],
            ['id' => $this->ids->create('blue-sheet'), 'private' => false],
            ['id' => $this->ids->create('datasheet'), 'private' => false],
            ['id' => $this->ids->create('datenblatt'), 'private' => false],
        ], $context);

        $blue = (new ProductBuilder($this->ids, 'drill-blue'))->build();
        $blue['documents'] = [['title' => 'Blue sheet', 'mediaId' => $this->ids->get('blue-sheet')]];

        $drill = (new ProductBuilder($this->ids, 'drill'))
            ->price(10)
            ->visibility($this->ids->get('sales-channel'))
            ->variant((new ProductBuilder($this->ids, 'drill-red'))->build())
            ->variant($blue)
            ->build();
        $drill['documents'] = [
            [
                'id' => $this->ids->create('manual-document'),
                'position' => 0,
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['title' => 'Manual', 'mediaId' => $this->ids->get('manual')],
                    $this->getDeDeLanguageId() => ['title' => 'Handbuch', 'mediaId' => $this->ids->get('handbuch')],
                ],
            ],
            ['position' => 1, 'title' => 'Certificate', 'mediaId' => $this->ids->get('certificate')],
        ];

        $saw = (new ProductBuilder($this->ids, 'saw'))->price(10)->visibility($this->ids->get('sales-channel'))->build();
        $saw['documents'] = [[
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['title' => 'Datasheet', 'mediaId' => $this->ids->get('datasheet')],
                $this->getDeDeLanguageId() => ['title' => 'Datenblatt', 'mediaId' => $this->ids->get('datenblatt')],
            ],
        ]];

        $hidden = (new ProductBuilder($this->ids, 'hidden'))->price(10)->build();

        static::getContainer()->get('product.repository')->create([$drill, $saw, $hidden], $context);

        static::getContainer()->get('media.repository')->update([
            ['id' => $this->ids->get('certificate'), 'private' => true],
            ['id' => $this->ids->get('datenblatt'), 'private' => true],
        ], $context);
    }
}
