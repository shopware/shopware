<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Content\Media\Subscriber\MediaDeletionSubscriber;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 *
 * @group slow
 * @group skip-paratest
 */
class MediaRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;

    private const FIXTURE_FILE = __DIR__ . '/../fixtures/shopware-logo.png';

    private EntityRepository $mediaRepository;

    private EntityRepository $documentRepository;

    private EntityRepository $orderRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->documentRepository = $this->getContainer()->get('document.repository');
        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testPrivateMediaNotReadable(): void
    {
        $mediaId = Uuid::randomHex();

        $this->mediaRepository->create(
            [
                [
                    'id' => $mediaId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $mediaId . '-' . (new \DateTime())->getTimestamp(),
                    'private' => true,
                ],
            ],
            $this->context
        );
        $mediaRepository = $this->mediaRepository;
        /** @var EntitySearchResult|null $media */
        $media = null;
        $this->context->scope(Context::USER_SCOPE, function () use ($mediaId, &$media, $mediaRepository): void {
            $media = $mediaRepository->search(new Criteria([$mediaId]), $this->context);
        });

        static::assertNotNull($media);
        static::assertEquals(0, $media->count());
    }

    public function testDeletePrivateMedia(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        $this->mediaRepository->create(
            [
                [
                    'id' => $ids->get('media'),
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $ids->get('media') . '-' . (new \DateTime())->getTimestamp(),
                    'private' => true,
                    'thumbnails' => [
                        [
                            'width' => 100,
                            'height' => 200,
                            'highDpi' => false,
                        ],
                    ],
                ],
            ],
            $context
        );

        $media = $this->getContainer()->get('media.repository')
            ->search(new Criteria([$ids->get('media')]), $context)
            ->first();

        static::assertInstanceOf(MediaEntity::class, $media);

        $fileSystem = $this->getContainer()->get('shopware.filesystem.private');

        $urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);

        $path = $urlGenerator->getRelativeMediaUrl($media);
        // simulate file
        $fileSystem->write($path, 'foo');

        // ensure that file system knows the file
        static::assertTrue($fileSystem->has($path));

        $context->addState(MediaDeletionSubscriber::SYNCHRONE_FILE_DELETE);
        $this->getContainer()->get('media.repository')
            ->delete([['id' => $ids->get('media')]], $context);

        // after deleting the entity, the file should be deleted too
        static::assertFalse($fileSystem->has($path));
    }

    public function testPrivateMediaReadableThroughAssociation(): void
    {
        $documentId = Uuid::randomHex();
        $documentTypeId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $folderId = Uuid::randomHex();
        $configId = Uuid::randomHex();

        $this->documentRepository->create(
            [
                [
                    'documentType' => [
                        'id' => $documentTypeId,
                        'technicalName' => 'testType',
                        'name' => 'test',
                    ],
                    'id' => $documentId,
                    'order' => $this->getOrderData($orderId),
                    'fileType' => 'pdf',
                    'config' => [],
                    'deepLinkCode' => 'deeplink',

                    'documentMediaFile' => [
                        'thumbnails' => [
                            [
                                'id' => Uuid::randomHex(),
                                'width' => 100,
                                'height' => 200,
                                'highDpi' => true,
                            ],
                        ],
                        'mediaFolder' => [
                            'id' => $folderId,
                            'name' => 'testFolder',
                            'configuration' => [
                                'id' => $configId,
                                'private' => true,
                            ],
                        ],

                        'id' => $mediaId,
                        'name' => 'test media',
                        'mimeType' => 'image/png',
                        'fileExtension' => 'png',
                        'fileName' => $mediaId . '-' . (new \DateTime())->getTimestamp(),
                        'private' => true,
                    ],
                ],
            ],
            $this->context
        );
        $mediaRepository = $this->mediaRepository;
        $media = $this->context->scope(Context::USER_SCOPE, fn (Context $context) => $mediaRepository->search(new Criteria([$mediaId]), $context));

        static::assertInstanceOf(EntitySearchResult::class, $media);
        static::assertEquals(0, $media->count());

        $documentRepository = $this->documentRepository;
        /** @var EntitySearchResult|null $document */
        $document = null;
        $this->context->scope(Context::USER_SCOPE, function (Context $context) use (&$document, $documentId, $documentRepository): void {
            $criteria = new Criteria([$documentId]);
            $criteria->addAssociation('documentMediaFile');
            $document = $documentRepository->search($criteria, $context);
        });
        static::assertNotNull($document);
        static::assertEquals(1, $document->count());
        $document = $document->get($documentId);
        static::assertInstanceOf(DocumentEntity::class, $document);
        $media = $document->getDocumentMediaFile();
        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertEquals($mediaId, $media->getId());
        static::assertEquals('', $media->getUrl());
        // currently there shouldn't be loaded any thumbnails for private media, but if, the urls should be blank
        foreach ($media->getThumbnails() ?? [] as $thumbnail) {
            static::assertEquals('', $thumbnail->getUrl());
        }
    }

    public function testPublicMediaIsReadable(): void
    {
        $mediaId = Uuid::randomHex();

        $this->mediaRepository->create(
            [
                [
                    'id' => $mediaId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $mediaId . '-' . (new \DateTime())->getTimestamp(),
                    'private' => false,
                ],
            ],
            $this->context
        );
        $media = $this->mediaRepository->search(new Criteria([$mediaId]), $this->context)->get($mediaId);

        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertEquals($mediaId, $media->getId());
    }

    public function testDeleteMediaEntityWithoutThumbnails(): void
    {
        $mediaId = Uuid::randomHex();

        $this->mediaRepository->create(
            [
                [
                    'id' => $mediaId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $mediaId . '-' . (new \DateTime())->getTimestamp(),
                ],
            ],
            $this->context
        );
        $media = $this->mediaRepository->search(new Criteria([$mediaId]), $this->context)->get($mediaId);
        static::assertInstanceOf(MediaEntity::class, $media);

        $mediaPath = $this->getContainer()->get(UrlGeneratorInterface::class)->getRelativeMediaUrl($media);

        $resource = fopen(self::FIXTURE_FILE, 'rb');
        static::assertNotFalse($resource);

        $this->getPublicFilesystem()->writeStream($mediaPath, $resource);

        $this->mediaRepository->delete([['id' => $mediaId]], $this->context);

        $this->runWorker();

        static::assertFalse($this->getPublicFilesystem()->has($mediaPath));
    }

    public function testDeleteMediaEntityWithThumbnails(): void
    {
        $mediaId = Uuid::randomHex();

        $this->mediaRepository->create(
            [
                [
                    'id' => $mediaId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $mediaId . '-' . (new \DateTime())->getTimestamp(),
                    'thumbnails' => [
                        [
                            'width' => 100,
                            'height' => 200,
                            'highDpi' => true,
                        ],
                    ],
                ],
            ],
            $this->context
        );
        $media = $this->mediaRepository->search(new Criteria([$mediaId]), $this->context)->get($mediaId);
        static::assertInstanceOf(MediaEntity::class, $media);

        $urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
        $mediaPath = $urlGenerator->getRelativeMediaUrl($media);
        $thumbnailPath = $urlGenerator->getRelativeThumbnailUrl($media, (new MediaThumbnailEntity())->assign(['width' => 100, 'height' => 200]));

        $resource = fopen(self::FIXTURE_FILE, 'rb');
        static::assertNotFalse($resource);

        $this->getPublicFilesystem()->writeStream($mediaPath, $resource);
        $resource = fopen(self::FIXTURE_FILE, 'rb');
        static::assertNotFalse($resource);

        $this->getPublicFilesystem()->writeStream($thumbnailPath, $resource);

        $this->mediaRepository->delete([['id' => $mediaId]], $this->context);

        $this->runWorker();

        static::assertFalse($this->getPublicFilesystem()->has($mediaPath));
        static::assertFalse($this->getPublicFilesystem()->has($thumbnailPath));
    }

    public function testDeleteMediaDeletesOnlyFilesForGivenMediaId(): void
    {
        $firstId = Uuid::randomHex();
        $secondId = Uuid::randomHex();

        $this->mediaRepository->create(
            [
                [
                    'id' => $firstId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $firstId . '-' . (new \DateTime())->getTimestamp(),
                ],
                [
                    'id' => $secondId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $secondId . '-' . (new \DateTime())->getTimestamp(),
                ],
            ],
            $this->context
        );

        $read = $this->mediaRepository->search(
            new Criteria(
                [
                    $firstId,
                    $secondId,
                ]
            ),
            $this->context
        );
        $firstMedia = $read->get($firstId);
        static::assertInstanceOf(MediaEntity::class, $firstMedia);
        $secondMedia = $read->get($secondId);
        static::assertInstanceOf(MediaEntity::class, $secondMedia);

        $urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
        $firstPath = $urlGenerator->getRelativeMediaUrl($firstMedia);
        $secondPath = $urlGenerator->getRelativeMediaUrl($secondMedia);

        $resource = fopen(self::FIXTURE_FILE, 'rb');
        static::assertNotFalse($resource);

        $this->getPublicFilesystem()->writeStream($firstPath, $resource);

        $resource = fopen(self::FIXTURE_FILE, 'rb');
        static::assertNotFalse($resource);

        $this->getPublicFilesystem()->writeStream($secondPath, $resource);

        $this->mediaRepository->delete([['id' => $firstId]], $this->context);

        $this->runWorker();

        static::assertFalse($this->getPublicFilesystem()->has($firstPath));
        static::assertTrue($this->getPublicFilesystem()->has($secondPath));
    }

    public function testDeleteForUnusedIds(): void
    {
        $firstId = Uuid::randomHex();

        $event = $this->mediaRepository->delete([['id' => $firstId]], $this->context);

        $this->runWorker();

        static::assertNull($event->getEventByEntityName(MediaDefinition::ENTITY_NAME));
    }

    public function testDeleteForEmptyIds(): void
    {
        $secondId = Uuid::randomHex();

        $this->mediaRepository->create(
            [
                [
                    'id' => $secondId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $secondId . '-' . (new \DateTime())->getTimestamp(),
                ],
            ],
            $this->context
        );

        $read = $this->mediaRepository->search(new Criteria([$secondId]), $this->context);
        $secondMedia = $read->get($secondId);
        static::assertInstanceOf(MediaEntity::class, $secondMedia);

        $urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
        $secondPath = $urlGenerator->getRelativeMediaUrl($secondMedia);

        $resource = fopen(self::FIXTURE_FILE, 'rb');
        static::assertNotFalse($resource);

        $this->getPublicFilesystem()->writeStream($secondPath, $resource);

        static::assertTrue($this->getPublicFilesystem()->has($secondPath));

        $event = $this->mediaRepository->delete([], $this->context);
        $this->runWorker();

        static::assertTrue($this->getPublicFilesystem()->has($secondPath));
        static::assertNull($event->getEventByEntityName(MediaDefinition::ENTITY_NAME));
    }

    public function testDeleteForMediaWithoutFile(): void
    {
        $firstId = Uuid::randomHex();

        $this->mediaRepository->create(
            [
                [
                    'id' => $firstId,
                    'name' => 'test media',
                ],
            ],
            $this->context
        );

        $event = $this->mediaRepository->delete([['id' => $firstId]], $this->context);

        $this->runWorker();

        $media = $event->getEventByEntityName(MediaDefinition::ENTITY_NAME);
        static::assertInstanceOf(EntityWrittenEvent::class, $media);
        static::assertCount(1, $media->getIds());
        static::assertEquals($firstId, $media->getIds()[0]);
    }

    public function testDeleteWithAlreadyDeletedFile(): void
    {
        $firstId = Uuid::randomHex();

        $this->mediaRepository->create(
            [
                [
                    'id' => $firstId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $firstId . '-' . (new \DateTime())->getTimestamp(),
                ],
            ],
            $this->context
        );

        $event = $this->mediaRepository->delete([['id' => $firstId]], $this->context);

        $this->runWorker();

        $media = $event->getEventByEntityName(MediaDefinition::ENTITY_NAME);
        static::assertInstanceOf(EntityWrittenEvent::class, $media);
        static::assertCount(1, $media->getIds());
        static::assertEquals($firstId, $media->getIds()[0]);
    }

    public function testItDoesNotDeleteFilesIfMediaHasDeleteRestrictions(): void
    {
        $mediaId = Uuid::randomHex();

        $cmsPageRepository = $this->getContainer()->get('cms_page.repository');

        $cmsPageRepository->create([[
            'name' => 'cms-page',
            'type' => 'page',
            'previewMedia' => [
                'id' => $mediaId,
                'name' => 'test media',
                'mimeType' => 'image/png',
                'fileExtension' => 'png',
                'fileName' => $mediaId . '-' . (new \DateTime())->getTimestamp(),
            ],
        ]], $this->context);

        $read = $this->mediaRepository->search(new Criteria([$mediaId]), $this->context);

        $media = $read->get($mediaId);
        static::assertInstanceOf(MediaEntity::class, $media);

        $urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
        $mediaUrl = $urlGenerator->getRelativeMediaUrl($media);

        $resource = fopen(self::FIXTURE_FILE, 'rb');
        static::assertNotFalse($resource);

        $this->getPublicFilesystem()->writeStream($mediaUrl, $resource);

        try {
            $this->mediaRepository->delete([['id' => $mediaId]], $this->context);
            static::fail('asserted DeleteRestrictViolationException');
        } catch (RestrictDeleteViolationException) {
            // ignore asserted exception
        }

        static::assertTrue($this->getPublicFilesystem()->has($mediaUrl));
    }

    public function testDeleteMediaEntityWithOrder(): void
    {
        $mediaId = Uuid::randomHex();
        $orderId = Uuid::randomHex();

        $this->mediaRepository->create(
            [
                [
                    'id' => $mediaId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $mediaId . '-' . (new \DateTime())->getTimestamp(),
                ],
            ],
            $this->context
        );

        $order = $this->getOrderData($orderId, $mediaId);

        $this->orderRepository->create([$order], $this->context);

        $event = $this->mediaRepository->delete([['id' => $mediaId]], $this->context)->getEventByEntityName(OrderLineItemDefinition::ENTITY_NAME);

        static::assertInstanceOf(EntityWrittenEvent::class, $event);
        $this->runWorker();

        $payload = $event->getPayloads()[0];

        static::assertEquals(OrderEvents::ORDER_LINE_ITEM_WRITTEN_EVENT, $event->getName());
        static::assertNull($payload['coverId']);
    }

    /**
     * @return array<string, mixed>
     */
    private function getOrderData(string $orderId, ?string $mediaId = null): array
    {
        $addressId = Uuid::randomHex();
        $orderLineItemId = Uuid::randomHex();
        $countryStateId = Uuid::randomHex();
        $salutation = $this->getValidSalutationId();

        return [
            'id' => $orderId,
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
            'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'stateId' => $this->getContainer()->get(InitialStateIdLoader::class)->get(OrderStates::STATE_MACHINE),
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'deliveries' => [
                [
                    'stateId' => $this->getContainer()->get(InitialStateIdLoader::class)->get(OrderDeliveryStates::STATE_MACHINE),
                    'shippingMethodId' => $this->getValidShippingMethodId(),
                    'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    'shippingDateEarliest' => date(\DATE_ISO8601),
                    'shippingDateLatest' => date(\DATE_ISO8601),
                    'shippingOrderAddress' => [
                        'salutationId' => $salutation,
                        'firstName' => 'Floy',
                        'lastName' => 'Glover',
                        'zipcode' => '59438-0403',
                        'city' => 'Stellaberg',
                        'street' => 'street',
                        'country' => [
                            'name' => 'kasachstan',
                            'id' => $this->getValidCountryId(),
                        ],
                    ],
                    'positions' => [
                        [
                            'price' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                            'orderLineItemId' => $orderLineItemId,
                        ],
                    ],
                ],
            ],
            'lineItems' => [
                [
                    'id' => $orderLineItemId,
                    'identifier' => 'test',
                    'quantity' => 1,
                    'type' => 'test',
                    'label' => 'test',
                    'price' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    'priceDefinition' => new QuantityPriceDefinition(10, new TaxRuleCollection()),
                    'good' => true,
                    'coverId' => $mediaId,
                ],
            ],
            'deepLinkCode' => 'BwvdEInxOHBbwfRw6oHF1Q_orfYeo9RY',
            'orderCustomer' => [
                'email' => 'test@example.com',
                'firstName' => 'Noe',
                'lastName' => 'Hill',
                'salutationId' => $salutation,
                'title' => 'Doc',
                'customerNumber' => 'Test',
                'customer' => [
                    'email' => 'test@example.com',
                    'firstName' => 'Noe',
                    'lastName' => 'Hill',
                    'salutationId' => $salutation,
                    'title' => 'Doc',
                    'customerNumber' => 'Test',
                    'guest' => true,
                    'group' => ['name' => 'testse2323'],
                    'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                    'salesChannelId' => TestDefaults::SALES_CHANNEL,
                    'defaultBillingAddressId' => $addressId,
                    'defaultShippingAddressId' => $addressId,
                    'addresses' => [
                        [
                            'id' => $addressId,
                            'salutationId' => $salutation,
                            'firstName' => 'Floy',
                            'lastName' => 'Glover',
                            'zipcode' => '59438-0403',
                            'city' => 'Stellaberg',
                            'street' => 'street',
                            'countryStateId' => $countryStateId,
                            'country' => [
                                'name' => 'kasachstan',
                                'id' => $this->getValidCountryId(),
                                'states' => [
                                    [
                                        'id' => $countryStateId,
                                        'name' => 'oklahoma',
                                        'shortCode' => 'OH',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'billingAddressId' => $addressId,
            'addresses' => [
                [
                    'salutationId' => $salutation,
                    'firstName' => 'Floy',
                    'lastName' => 'Glover',
                    'zipcode' => '59438-0403',
                    'city' => 'Stellaberg',
                    'street' => 'street',
                    'countryId' => $this->getValidCountryId(),
                    'id' => $addressId,
                ],
            ],
        ];
    }
}
