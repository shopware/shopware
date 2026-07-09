<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\RevocationRequest\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\LandingPage\LandingPageCollection;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\RevocationRequest\Event\RevocationRequestEvent;
use Shopware\Core\Content\RevocationRequest\SalesChannel\RevocationRequestRoute;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(RevocationRequestRoute::class)]
class RevocationRequestRouteTest extends TestCase
{
    public function testRequestShouldReturnCategorySuccessMessage(): void
    {
        $successMessage = 'category success message';
        $slotId = Uuid::randomHex();
        $category = new CategoryEntity();
        $category->setId(Uuid::randomHex());
        $category->setSlotConfig($this->createSlotConfig($slotId, $successMessage));

        $dataBag = new RequestDataBag($this->createValidFormData($slotId, Uuid::randomHex()));

        $revocationRequestRoute = $this->createRevocationRequestRoute(categoryEntities: [$category]);

        $result = $revocationRequestRoute->request($dataBag, $this->createSalesChannelContext());

        static::assertSame($successMessage, $result->getIndividualSuccessMessage());
    }

    public function testRequestShouldReturnCmsSlotSuccessMessage(): void
    {
        $successMessage = 'cms slot success message';

        $slotId = Uuid::randomHex();
        $config = $this->createSlotConfig($slotId, $successMessage);

        $cmsSlot = new CmsSlotEntity();
        $cmsSlot->setId($slotId);
        $cmsSlot->setTranslated(['config' => $config[$slotId]]);

        $formData = $this->createValidFormData($slotId);
        $dataBag = new RequestDataBag($formData);

        $result = $this->createRevocationRequestRoute([$cmsSlot])->request($dataBag, $this->createSalesChannelContext());

        static::assertSame($successMessage, $result->getIndividualSuccessMessage());
    }

    public function testRequestShouldReturnLandingPageSuccessMessage(): void
    {
        $successMessage = 'landing page success message';
        $slotId = Uuid::randomHex();

        $landingPage = new LandingPageEntity();
        $landingPage->setId(Uuid::randomHex());
        $landingPage->setSlotConfig($this->createSlotConfig($slotId, $successMessage, ['landing-page@example.com']));

        $dispatchedEvent = null;
        $eventDispatcher = $this->createEventDispatcherCapturingRevocationRequestEvent($dispatchedEvent);

        $dataBag = new RequestDataBag($this->createValidFormData($slotId, $landingPage->getId(), LandingPageDefinition::ENTITY_NAME));

        $revocationRequestRoute = $this->createRevocationRequestRoute(
            landingPageEntities: [$landingPage],
            eventDispatcher: $eventDispatcher,
        );

        $result = $revocationRequestRoute->request($dataBag, $this->createSalesChannelContext());

        static::assertSame($successMessage, $result->getIndividualSuccessMessage());
        static::assertInstanceOf(RevocationRequestEvent::class, $dispatchedEvent);
        static::assertSame(['landing-page@example.com' => 'landing-page@example.com'], $dispatchedEvent->getMailStruct()->getRecipients());
    }

    public function testRequestShouldReturnProductSuccessMessage(): void
    {
        $successMessage = 'product success message';
        $slotId = Uuid::randomHex();

        $product = new ProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setSlotConfig($this->createSlotConfig($slotId, $successMessage, ['product@example.com']));

        $dispatchedEvent = null;
        $eventDispatcher = $this->createEventDispatcherCapturingRevocationRequestEvent($dispatchedEvent);

        $dataBag = new RequestDataBag($this->createValidFormData($slotId, $product->getId(), ProductDefinition::ENTITY_NAME));

        $revocationRequestRoute = $this->createRevocationRequestRoute(
            productEntities: [$product],
            eventDispatcher: $eventDispatcher,
        );

        $result = $revocationRequestRoute->request($dataBag, $this->createSalesChannelContext());

        static::assertSame($successMessage, $result->getIndividualSuccessMessage());
        static::assertInstanceOf(RevocationRequestEvent::class, $dispatchedEvent);
        static::assertSame(['product@example.com' => 'product@example.com'], $dispatchedEvent->getMailStruct()->getRecipients());
    }

    /**
     * @param array<string, array{value: array<int, string>|string}> $slotConfig
     */
    #[DataProvider('partialSlotConfigProvider')]
    public function testRequestShouldUseInheritedSlotConfigFieldsIndependently(array $slotConfig, string $expectedRecipient, string $expectedSuccessMessage): void
    {
        $slotId = Uuid::randomHex();

        $cmsSlot = new CmsSlotEntity();
        $cmsSlot->setId($slotId);
        $cmsSlot->setTranslated(['config' => $this->createSlotConfig($slotId, 'Inherited success message', ['inherited@example.com'])[$slotId]]);

        $landingPage = new LandingPageEntity();
        $landingPage->setId(Uuid::randomHex());
        $landingPage->setSlotConfig([$slotId => $slotConfig]);

        $dispatchedEvent = null;
        $eventDispatcher = $this->createEventDispatcherCapturingRevocationRequestEvent($dispatchedEvent);

        $dataBag = new RequestDataBag($this->createValidFormData($slotId, $landingPage->getId(), LandingPageDefinition::ENTITY_NAME));

        $revocationRequestRoute = $this->createRevocationRequestRoute(
            slotEntities: [$cmsSlot],
            landingPageEntities: [$landingPage],
            eventDispatcher: $eventDispatcher,
        );

        $result = $revocationRequestRoute->request($dataBag, $this->createSalesChannelContext());

        static::assertSame($expectedSuccessMessage, $result->getIndividualSuccessMessage());
        static::assertInstanceOf(RevocationRequestEvent::class, $dispatchedEvent);
        static::assertArrayHasKey($expectedRecipient, $dispatchedEvent->getMailStruct()->getRecipients());
    }

    public static function partialSlotConfigProvider(): \Generator
    {
        yield 'custom receiver inherits confirmation text' => [
            [
                'mailReceiver' => [
                    'value' => ['child@example.com'],
                ],
            ],
            'child@example.com',
            'Inherited success message',
        ];

        yield 'custom confirmation text inherits receiver' => [
            [
                'confirmationText' => [
                    'value' => 'Child success message',
                ],
            ],
            'inherited@example.com',
            'Child success message',
        ];

        yield 'empty custom confirmation text is not inherited' => [
            [
                'confirmationText' => [
                    'value' => '',
                ],
            ],
            'inherited@example.com',
            '',
        ];
    }

    public function testRequestWithoutSlotIdShouldReturnDefaultsMessage(): void
    {
        $successMessage = '';

        $formData = $this->createValidFormData();
        $dataBag = new RequestDataBag($formData);

        $result = $this->createRevocationRequestRoute()->request($dataBag, $this->createSalesChannelContext());

        static::assertSame($successMessage, $result->getIndividualSuccessMessage());
    }

    public function testRequestWithoutSlotEntityShouldReturnDefaultsMessage(): void
    {
        $successMessage = '';

        $slotId = Uuid::randomHex();

        $formData = $this->createValidFormData($slotId);
        $dataBag = new RequestDataBag($formData);

        $result = $this->createRevocationRequestRoute()->request($dataBag, $this->createSalesChannelContext());

        static::assertSame($successMessage, $result->getIndividualSuccessMessage());
    }

    public function testRequestWithSlotEntityWithoutTranslationShouldReturnDefaultsMessage(): void
    {
        $successMessage = '';

        $slotId = Uuid::randomHex();

        $config = $this->createSlotConfig($slotId, $successMessage);

        $formData = $this->createValidFormData($slotId);
        $dataBag = new RequestDataBag($formData);

        $cmsSlot = new CmsSlotEntity();
        $cmsSlot->setId($slotId);
        $cmsSlot->setTranslated(['config' => $config[$slotId]]);

        $result = $this->createRevocationRequestRoute([$cmsSlot])->request($dataBag, $this->createSalesChannelContext());

        static::assertSame($successMessage, $result->getIndividualSuccessMessage());
    }

    public function createValidatorMock(): DataValidator&Stub
    {
        $validatorMock = static::createStub(DataValidator::class);

        $validatorMock->method('getViolations')->willReturnCallback(static function (): ConstraintViolationList {
            return new ConstraintViolationList();
        });

        return $validatorMock;
    }

    public function createRequestStackMock(): RequestStack&Stub
    {
        $requestStackMock = static::createStub(RequestStack::class);
        $requestStackMock->method('getMainRequest')->willReturn(new Request());

        return $requestStackMock;
    }

    /**
     * @param array<int, CmsSlotEntity>|null $slotEntities
     * @param array<int, CategoryEntity>|null $categoryEntities
     * @param array<int, LandingPageEntity>|null $landingPageEntities
     * @param array<int, ProductEntity>|null $productEntities
     */
    private function createRevocationRequestRoute(
        ?array $slotEntities = [],
        ?array $categoryEntities = [],
        ?array $landingPageEntities = [],
        ?array $productEntities = [],
        ?EventDispatcherInterface $eventDispatcher = null,
    ): RevocationRequestRoute {
        $validatorFactoryMock = static::createStub(DataValidationFactoryInterface::class);

        $validatorMock = $this->createValidatorMock();

        $requestStackMock = $this->createRequestStackMock();

        $rateLimiterMock = static::createStub(RateLimiter::class);
        $eventDispatcherMock = $eventDispatcher ?? static::createStub(EventDispatcherInterface::class);
        $systemConfigServiceMock = static::createStub(SystemConfigService::class);

        /** @var StaticEntityRepository<CmsSlotCollection> $cmsSlotRepository */
        $cmsSlotRepository = new StaticEntityRepository([$slotEntities], new CmsSlotDefinition());
        /** @var StaticEntityRepository<CategoryCollection> $categoryRepository */
        $categoryRepository = new StaticEntityRepository([$categoryEntities], new CategoryDefinition());
        /** @var StaticEntityRepository<LandingPageCollection> $landingPageRepository */
        $landingPageRepository = new StaticEntityRepository([$landingPageEntities], new LandingPageDefinition());
        /** @var StaticEntityRepository<ProductCollection> $productRepository */
        $productRepository = new StaticEntityRepository([$productEntities], new ProductDefinition());

        return new RevocationRequestRoute(
            $validatorFactoryMock,
            $validatorMock,
            $requestStackMock,
            $rateLimiterMock,
            $eventDispatcherMock,
            $systemConfigServiceMock,
            $cmsSlotRepository,
            $categoryRepository,
            new NativeClock(),
            $landingPageRepository,
            $productRepository,
        );
    }

    private function createSalesChannelContext(): SalesChannelContext
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());

        return Generator::generateSalesChannelContext(
            baseContext: new Context(new SalesChannelApiSource(Uuid::randomHex())),
            salesChannel: $salesChannel
        );
    }

    /**
     * @param array<int, string> $receivers
     *
     * @return array<string, array{
     *     mailReceiver: array{value: array<int, string>},
     *     confirmationText: array{value: string}
     * }>
     */
    private function createSlotConfig(string $slotId, string $successMessage, array $receivers = ['admin']): array
    {
        return [$slotId => ['mailReceiver' => ['value' => $receivers], 'confirmationText' => ['value' => $successMessage]]];
    }

    private function createEventDispatcherCapturingRevocationRequestEvent(?RevocationRequestEvent &$dispatchedEvent): EventDispatcherInterface
    {
        $eventDispatcher = static::createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnCallback(static function (object $event, ?string $eventName = null) use (&$dispatchedEvent): object {
            if ($event instanceof RevocationRequestEvent) {
                $dispatchedEvent = $event;
            }

            return $event;
        });

        return $eventDispatcher;
    }

    /**
     * @return array{
     *     firstName: string,
     *     lastName: string,
     *     email: string,
     *     contractNumber: string,
     *     comment: string,
     *     slotId?: string,
     *     navigationId?: string,
     *     entityName?: string,
     * }
     */
    private function createValidFormData(?string $cmsSlotId = null, ?string $navigationId = null, ?string $entityName = null): array
    {
        $forData = [
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'email' => 'max@muster.com',
            'contractNumber' => 'SW123456789',
            'comment' => 'This is a simple comment',
        ];

        if ($cmsSlotId !== null) {
            $forData['slotId'] = $cmsSlotId;
        }

        if ($navigationId !== null) {
            $forData['navigationId'] = $navigationId;
        }

        if ($entityName !== null) {
            $forData['entityName'] = $entityName;
        }

        return $forData;
    }
}
