<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Capability\Fulfillment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Ucp\Capability\Fulfillment\FulfillmentMapper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Pins the buyer-selected-fulfillment resolution paths. The mapper accepts
 * three valid UCP shapes for the selection patch (`selected_option_id`,
 * `methods[].groups[].selected_option_id`) and must reject invalid UUIDs or
 * unknown ids without touching the cart context.
 *
 * @internal
 */
#[CoversClass(FulfillmentMapper::class)]
class FulfillmentSelectionTest extends TestCase
{
    public function testResolvesFlatSelectedOptionIdWhenActive(): void
    {
        $uuid = Uuid::randomHex();
        $repo = $this->createMock(EntityRepository::class);
        $repo->expects($this->once())
            ->method('searchIds')
            ->willReturn(new IdSearchResult(1, [$uuid => ['primaryKey' => $uuid, 'data' => []]], new Criteria(), Context::createDefaultContext()));

        $mapper = new FulfillmentMapper($repo);
        $resolved = $mapper->resolveSelection(
            ['selected_option_id' => $uuid],
            $this->context()
        );

        static::assertSame($uuid, $resolved);
    }

    public function testResolvesNestedShippingMethodIdFromGroupShape(): void
    {
        $uuid = Uuid::randomHex();
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('searchIds')->willReturn(new IdSearchResult(1, [$uuid => ['primaryKey' => $uuid, 'data' => []]], new Criteria(), Context::createDefaultContext()));

        $resolved = (new FulfillmentMapper($repo))->resolveSelection(
            [
                'methods' => [[
                    'groups' => [[
                        'selected_option_id' => $uuid,
                    ]],
                ]],
            ],
            $this->context()
        );

        static::assertSame($uuid, $resolved);
    }

    public function testReturnsNullWhenNoSelectedOptionAnywhere(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->expects($this->never())->method('searchIds');

        $resolved = (new FulfillmentMapper($repo))->resolveSelection(
            ['methods' => []],
            $this->context()
        );

        static::assertNull($resolved);
    }

    public function testReturnsNullForNonUuidSelectedOption(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        // Bail before any DB roundtrip — invalid UUIDs never reach the repo.
        $repo->expects($this->never())->method('searchIds');

        $resolved = (new FulfillmentMapper($repo))->resolveSelection(
            ['selected_option_id' => 'not-a-uuid'],
            $this->context()
        );

        static::assertNull($resolved);
    }

    public function testReturnsNullForInactiveOrUnknownShippingMethod(): void
    {
        $uuid = Uuid::randomHex();
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('searchIds')->willReturn(new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext()));

        $resolved = (new FulfillmentMapper($repo))->resolveSelection(
            ['selected_option_id' => $uuid],
            $this->context()
        );

        static::assertNull($resolved);
    }

    private function context(): SalesChannelContext
    {
        $ctx = $this->createMock(SalesChannelContext::class);
        $ctx->method('getContext')->willReturn(Context::createDefaultContext());

        return $ctx;
    }
}
