<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\RevocationRequest\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
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

    public function createValidatorMock(): DataValidator&MockObject
    {
        $validatorMock = $this->createMock(DataValidator::class);

        $validatorMock->method('getViolations')->willReturnCallback(static function (): ConstraintViolationList {
            return new ConstraintViolationList();
        });

        return $validatorMock;
    }

    public function createRequestStackMock(): RequestStack&MockObject
    {
        $requestStackMock = $this->createMock(RequestStack::class);
        $requestStackMock->method('getMainRequest')->willReturn(new Request());

        return $requestStackMock;
    }

    /**
     * @param array<int, CmsSlotEntity>|null $slotEntities
     * @param array<int, CategoryEntity>|null $categoryEntities
     */
    private function createRevocationRequestRoute(?array $slotEntities = [], ?array $categoryEntities = []): RevocationRequestRoute
    {
        $validatorFactoryMock = $this->createMock(DataValidationFactoryInterface::class);

        $validatorMock = $this->createValidatorMock();

        $requestStackMock = $this->createRequestStackMock();

        $rateLimiterMock = $this->createMock(RateLimiter::class);
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $systemConfigServiceMock = $this->createMock(SystemConfigService::class);

        /** @var StaticEntityRepository<CmsSlotCollection> $cmsSlotRepository */
        $cmsSlotRepository = new StaticEntityRepository([$slotEntities], new CmsSlotDefinition());
        /** @var StaticEntityRepository<CategoryCollection> $categoryRepository */
        $categoryRepository = new StaticEntityRepository([$categoryEntities], new CategoryDefinition());

        return new RevocationRequestRoute(
            $validatorFactoryMock,
            $validatorMock,
            $requestStackMock,
            $rateLimiterMock,
            $eventDispatcherMock,
            $systemConfigServiceMock,
            $cmsSlotRepository,
            $categoryRepository,
            new NativeClock()
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
     * @return array<string, array{
     *     mailReceiver: array{value: string},
     *     confirmationText: array{value: string}
     * }>
     */
    private function createSlotConfig(string $slotId, string $successMessage): array
    {
        return [$slotId => ['mailReceiver' => ['value' => 'admin'], 'confirmationText' => ['value' => $successMessage]]];
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
     * }
     */
    private function createValidFormData(?string $cmsSlotId = null, ?string $navigationId = null): array
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

        return $forData;
    }
}
