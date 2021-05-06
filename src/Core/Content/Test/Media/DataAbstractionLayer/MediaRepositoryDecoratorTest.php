<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\StateMachineRegistry;

/**
 * @group slow
 * @group skip-paratest
 */
class MediaRepositoryDecoratorTest extends TestCase
{
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;

    private const FIXTURE_FILE = __DIR__ . '/../fixtures/shopware-logo.png';

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $documentRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->documentRepository = $this->getContainer()->get('document.repository');
        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->context = Context::createDefaultContext();
        $this->stateMachineRegistry = $this->getContainer()->get(StateMachineRegistry::class);
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
                    'order' => $this->getOrderData($orderId, $this->context)[0],
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
        /** @var EntitySearchResult|null $media */
        $media = null;
        $this->context->scope(Context::USER_SCOPE, function (Context $context) use ($mediaId, &$media, $mediaRepository): void {
            $media = $mediaRepository->search(new Criteria([$mediaId]), $context);
        });

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
        static::assertEquals($mediaId, $document->get($documentId)->getDocumentMediaFile()->getId());
        static::assertEquals('', $document->get($documentId)->getDocumentMediaFile()->getUrl());
        // currently there shouldn't be loaded any thumbnails for private media, but if, the urls should be blank
        foreach ($document->get($documentId)->getDocumentMediaFile()->getThumbnails() as $thumbnail) {
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

        $mediaPath = $this->getContainer()->get(UrlGeneratorInterface::class)->getRelativeMediaUrl($media);

        $this->getPublicFilesystem()->putStream($mediaPath, fopen(self::FIXTURE_FILE, 'rb'));

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

        $urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
        $mediaPath = $urlGenerator->getRelativeMediaUrl($media);
        $thumbnailPath = $urlGenerator->getRelativeThumbnailUrl($media, (new MediaThumbnailEntity())->assign(['width' => 100, 'height' => 200]));

        $this->getPublicFilesystem()->putStream($mediaPath, fopen(self::FIXTURE_FILE, 'rb'));
        $this->getPublicFilesystem()->putStream($thumbnailPath, fopen(self::FIXTURE_FILE, 'rb'));

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
        $secondMedia = $read->get($secondId);

        $urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
        $firstPath = $urlGenerator->getRelativeMediaUrl($firstMedia);
        $secondPath = $urlGenerator->getRelativeMediaUrl($secondMedia);

        $this->getPublicFilesystem()->putStream($firstPath, fopen(self::FIXTURE_FILE, 'rb'));
        $this->getPublicFilesystem()->putStream($secondPath, fopen(self::FIXTURE_FILE, 'rb'));

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

        static::assertCount(1, $event->getEventByEntityName(MediaDefinition::ENTITY_NAME)->getIds());
        static::assertEquals($firstId, $event->getEventByEntityName(MediaDefinition::ENTITY_NAME)->getIds()[0]);
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

        static::assertCount(1, $event->getEventByEntityName(MediaDefinition::ENTITY_NAME)->getIds());
        static::assertEquals($firstId, $event->getEventByEntityName(MediaDefinition::ENTITY_NAME)->getIds()[0]);
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

        $urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
        $mediaUrl = $urlGenerator->getRelativeMediaUrl($media);

        $this->getPublicFilesystem()->putStream($mediaUrl, fopen(self::FIXTURE_FILE, 'rb'));

        try {
            $this->mediaRepository->delete([['id' => $mediaId]], $this->context);
            static::fail('asserted DeleteRestrictViolationException');
        } catch (RestrictDeleteViolationException $e) {
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

        $order = $this->getOrderData($orderId, $this->context, $mediaId);

        $this->orderRepository->create($order, $this->context);

        $event = $this->mediaRepository->delete([['id' => $mediaId]], $this->context)->getEventByEntityName(OrderLineItemDefinition::ENTITY_NAME);

        $this->runWorker();

        $payload = $event->getPayloads()[0];

        static::assertEquals(OrderEvents::ORDER_LINE_ITEM_WRITTEN_EVENT, $event->getName());
        static::assertNull($payload['coverId']);
    }

    private function getOrderData(string $orderId, Context $context, ?string $mediaId = null): array
    {
        $addressId = Uuid::randomHex();
        $orderLineItemId = Uuid::randomHex();
        $countryStateId = Uuid::randomHex();
        $salutation = $this->getValidSalutationId();

        $order = [
            [
                'id' => $orderId,
                'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
                'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                'stateId' => $this->stateMachineRegistry->getInitialState(OrderStates::STATE_MACHINE, $context)->getId(),
                'paymentMethodId' => $this->getValidPaymentMethodId(),
                'currencyId' => Defaults::CURRENCY,
                'currencyFactor' => 1,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'deliveries' => [
                    [
                        'stateId' => $this->stateMachineRegistry->getInitialState(OrderDeliveryStates::STATE_MACHINE, $context)->getId(),
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
                        'salesChannelId' => Defaults::SALES_CHANNEL,
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
            ],
        ]
        ;

        return $order;
    }
}
