<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\ProductAssignment\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\ProductAssignment\Api\SalesChannelProductAssignmentController;
use Shopware\Core\System\SalesChannel\ProductAssignment\Api\SalesChannelProductAssignmentRequest;
use Shopware\Core\System\SalesChannel\ProductAssignment\SalesChannelProductAssigner;
use Shopware\Core\System\SalesChannel\ProductAssignment\SalesChannelProductAssignmentResult;
use Shopware\Core\System\SalesChannel\SalesChannelException;

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

    private SalesChannelProductAssignmentController $controller;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->assigner = $this->createMock(SalesChannelProductAssigner::class);
        $this->controller = new SalesChannelProductAssignmentController($this->assigner);
    }

    public function testForwardsEverySourceWithTheRequestedVisibility(): void
    {
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
            new SalesChannelProductAssignmentRequest(
                productIds: ['product-a', 'product-b'],
                productStreamIds: ['stream-a'],
                categoryIds: ['category-a'],
                visibility: ProductVisibilityDefinition::VISIBILITY_LINK,
            ),
            $this->context,
        );

        static::assertSame('{"assigned":3,"complete":true}', $response->getContent());
    }

    public function testDefaultsToEmptySourcesAndFullVisibility(): void
    {
        $this->assigner
            ->expects($this->once())
            ->method('assign')
            ->with(self::SALES_CHANNEL_ID, [], [], [], ProductVisibilityDefinition::VISIBILITY_ALL, $this->context)
            ->willReturn(new SalesChannelProductAssignmentResult(0, complete: true));

        $response = $this->controller->assign(self::SALES_CHANNEL_ID, new SalesChannelProductAssignmentRequest(), $this->context);

        static::assertSame('{"assigned":0,"complete":true}', $response->getContent());
    }

    public function testRequiresReadPrivilegesForTheNamedSources(): void
    {
        $this->assigner->expects($this->never())->method('assign');

        $this->expectExceptionObject(SalesChannelException::missingPrivileges(['product_stream:read', 'category:read']));

        $this->controller->assign(
            self::SALES_CHANNEL_ID,
            new SalesChannelProductAssignmentRequest(productStreamIds: ['stream-a'], categoryIds: ['category-a']),
            $this->contextWithPermissions(['product_visibility:create', 'product:read']),
        );
    }

    public function testAcceptsSourcesTheCallerMayRead(): void
    {
        $context = $this->contextWithPermissions(['product_visibility:create', 'product:read', 'product_stream:read']);

        $this->assigner
            ->expects($this->once())
            ->method('assign')
            ->with(self::SALES_CHANNEL_ID, [], ['stream-a'], [], ProductVisibilityDefinition::VISIBILITY_ALL, $context)
            ->willReturn(new SalesChannelProductAssignmentResult(2, complete: false));

        $response = $this->controller->assign(
            self::SALES_CHANNEL_ID,
            new SalesChannelProductAssignmentRequest(productStreamIds: ['stream-a']),
            $context,
        );

        static::assertSame('{"assigned":2,"complete":false}', $response->getContent());
    }

    /**
     * @param list<string> $permissions
     */
    private function contextWithPermissions(array $permissions): Context
    {
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setIsAdmin(false);
        $source->setPermissions($permissions);

        return Context::createDefaultContext($source);
    }
}
