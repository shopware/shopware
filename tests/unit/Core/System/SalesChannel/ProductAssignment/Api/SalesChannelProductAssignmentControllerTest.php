<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\ProductAssignment\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\ProductAssignment\Api\SalesChannelProductAssignmentController;
use Shopware\Core\System\SalesChannel\ProductAssignment\SalesChannelProductAssigner;
use Shopware\Core\System\SalesChannel\ProductAssignment\SalesChannelProductAssignmentResult;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelProductAssignmentController::class)]
class SalesChannelProductAssignmentControllerTest extends TestCase
{
    private const SALES_CHANNEL_ID = '018f0b4c6d1e7f2a9b3c4d5e6f708192';

    private Context $context;

    private SalesChannelProductAssigner&MockObject $assigner;

    private DataValidator&MockObject $validator;

    private SalesChannelProductAssignmentController $controller;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->assigner = $this->createMock(SalesChannelProductAssigner::class);
        $this->validator = $this->createMock(DataValidator::class);
        $this->controller = new SalesChannelProductAssignmentController($this->assigner, $this->validator);
    }

    public function testForwardsEverySourceOnceWithTheRequestedVisibility(): void
    {
        $this->validator->expects($this->once())->method('validate');
        $this->assigner
            ->expects($this->once())
            ->method('assign')
            ->with(
                self::SALES_CHANNEL_ID,
                ['product-a', 'product-b'],
                ['stream-a'],
                ['category-a'],
                ProductVisibilityDefinition::VISIBILITY_LINK,
                $this->context,
            )
            ->willReturn(new SalesChannelProductAssignmentResult(3, complete: true));

        $response = $this->controller->assign(
            self::SALES_CHANNEL_ID,
            new RequestDataBag([
                'productIds' => ['product-a', 'product-a', 'product-b'],
                'productStreamIds' => ['stream-a'],
                'categoryIds' => ['category-a'],
                'visibility' => ProductVisibilityDefinition::VISIBILITY_LINK,
            ]),
            $this->context,
        );

        static::assertSame('{"assigned":3,"complete":true}', $response->getContent());
    }

    public function testDefaultsToEmptySourcesAndFullVisibility(): void
    {
        $this->validator->expects($this->once())->method('validate');
        $this->assigner
            ->expects($this->once())
            ->method('assign')
            ->with(self::SALES_CHANNEL_ID, [], [], [], ProductVisibilityDefinition::VISIBILITY_ALL, $this->context)
            ->willReturn(new SalesChannelProductAssignmentResult(0, complete: true));

        $response = $this->controller->assign(self::SALES_CHANNEL_ID, new RequestDataBag(), $this->context);

        static::assertSame('{"assigned":0,"complete":true}', $response->getContent());
    }

    public function testRequiresReadPrivilegesForTheNamedSources(): void
    {
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setIsAdmin(false);
        $source->setPermissions(['product_visibility:create', 'product:read']);
        $context = Context::createDefaultContext($source);

        $this->validator->expects($this->once())->method('validate');
        $this->assigner->expects($this->never())->method('assign');

        $this->expectExceptionObject(SalesChannelException::missingPrivileges(['product_stream:read', 'category:read']));

        $this->controller->assign(
            self::SALES_CHANNEL_ID,
            new RequestDataBag([
                'productStreamIds' => ['stream-a'],
                'categoryIds' => ['category-a'],
            ]),
            $context,
        );
    }

    public function testAcceptsSourcesTheCallerMayRead(): void
    {
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setIsAdmin(false);
        $source->setPermissions(['product_visibility:create', 'product:read', 'product_stream:read']);
        $context = Context::createDefaultContext($source);

        $this->validator->expects($this->once())->method('validate');
        $this->assigner
            ->expects($this->once())
            ->method('assign')
            ->with(self::SALES_CHANNEL_ID, [], ['stream-a'], [], ProductVisibilityDefinition::VISIBILITY_ALL, $context)
            ->willReturn(new SalesChannelProductAssignmentResult(2, complete: false));

        $response = $this->controller->assign(
            self::SALES_CHANNEL_ID,
            new RequestDataBag(['productStreamIds' => ['stream-a']]),
            $context,
        );

        static::assertSame('{"assigned":2,"complete":false}', $response->getContent());
    }

    public function testValidatesIdListsAndVisibilityBeforeAssigning(): void
    {
        $data = ['productIds' => ['not-a-uuid'], 'visibility' => 15];
        $exception = new ConstraintViolationException(new ConstraintViolationList(), $data);

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->with($data, static::callback(static function (DataValidationDefinition $definition): bool {
                return array_keys($definition->getProperties()) === [
                    'productIds',
                    'productStreamIds',
                    'categoryIds',
                    'visibility',
                ];
            }))
            ->willThrowException($exception);
        $this->assigner->expects($this->never())->method('assign');

        $this->expectExceptionObject($exception);

        $this->controller->assign(self::SALES_CHANNEL_ID, new RequestDataBag($data), $this->context);
    }

    #[DataProvider('idListLimitProvider')]
    public function testRejectsIdListsOverTheirLimit(string $key, int $limit): void
    {
        $this->validator->expects($this->never())->method('validate');
        $this->assigner->expects($this->never())->method('assign');

        try {
            $this->controllerWithRealValidator()->assign(
                self::SALES_CHANNEL_ID,
                new RequestDataBag([$key => $this->randomIds($limit + 1)]),
                $this->context,
            );
            static::fail(\sprintf('%d entries in %s should have been rejected', $limit + 1, $key));
        } catch (ConstraintViolationException $exception) {
            static::assertCount(1, $exception->getViolations('/' . $key));
        }
    }

    #[DataProvider('idListLimitProvider')]
    public function testAcceptsIdListsAtTheirLimit(string $key, int $limit): void
    {
        $this->validator->expects($this->never())->method('validate');
        $this->assigner->expects($this->once())->method('assign')->willReturn(new SalesChannelProductAssignmentResult($limit, complete: true));

        $response = $this->controllerWithRealValidator()->assign(
            self::SALES_CHANNEL_ID,
            new RequestDataBag([$key => $this->randomIds($limit)]),
            $this->context,
        );

        static::assertSame(\sprintf('{"assigned":%d,"complete":true}', $limit), $response->getContent());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function idListLimitProvider(): iterable
    {
        yield 'explicit product ids get the highest limit' => ['productIds', SalesChannelProductAssignmentController::MAX_PRODUCT_IDS];
        yield 'product groups are capped low because each one scans the catalog' => ['productStreamIds', SalesChannelProductAssignmentController::MAX_PRODUCT_STREAM_IDS];
        yield 'categories are capped low because each one is resolved separately' => ['categoryIds', SalesChannelProductAssignmentController::MAX_CATEGORY_IDS];
    }

    private function controllerWithRealValidator(): SalesChannelProductAssignmentController
    {
        return new SalesChannelProductAssignmentController(
            $this->assigner,
            new DataValidator(Validation::createValidator()),
        );
    }

    /**
     * @return list<string>
     */
    private function randomIds(int $count): array
    {
        return array_map(static fn (): string => Uuid::randomHex(), range(1, $count));
    }
}
