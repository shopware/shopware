<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\ProductAssignment\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\ProductAssignment\Api\SalesChannelProductAssignmentRequest;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelProductAssignmentRequest::class)]
class SalesChannelProductAssignmentRequestTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testDefaultsToEmptySourcesAndFullVisibility(): void
    {
        $request = new SalesChannelProductAssignmentRequest();

        static::assertSame([], $request->productIds);
        static::assertSame([], $request->productStreamIds);
        static::assertSame([], $request->categoryIds);
        static::assertSame(ProductVisibilityDefinition::VISIBILITY_ALL, $request->visibility);
        static::assertCount(0, $this->validator->validate($request));
    }

    public function testRejectsIdsThatAreNotUuidsAndUnknownVisibilities(): void
    {
        $request = new SalesChannelProductAssignmentRequest(
            productIds: ['not-a-uuid'],
            productStreamIds: [Uuid::randomHex(), 'not-a-uuid'],
            categoryIds: ['not-a-uuid'],
            visibility: 15,
        );

        static::assertSame(
            ['productIds[0]', 'productStreamIds[1]', 'categoryIds[0]', 'visibility'],
            $this->violatedPaths($request),
        );
    }

    #[DataProvider('idListLimitProvider')]
    public function testRejectsIdListsOverTheirLimit(string $list, int $limit): void
    {
        $request = new SalesChannelProductAssignmentRequest(...[$list => $this->ids($limit + 1)]);

        static::assertSame([$list], $this->violatedPaths($request));
    }

    #[DataProvider('idListLimitProvider')]
    public function testAcceptsIdListsAtTheirLimit(string $list, int $limit): void
    {
        $request = new SalesChannelProductAssignmentRequest(...[$list => $this->ids($limit)]);

        static::assertSame([], $this->violatedPaths($request));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function idListLimitProvider(): iterable
    {
        yield 'explicit product ids get the highest limit' => ['productIds', SalesChannelProductAssignmentRequest::MAX_PRODUCT_IDS];
        yield 'product groups are capped low because each one scans the catalog' => ['productStreamIds', SalesChannelProductAssignmentRequest::MAX_PRODUCT_STREAM_IDS];
        yield 'categories are capped low because each one is resolved separately' => ['categoryIds', SalesChannelProductAssignmentRequest::MAX_CATEGORY_IDS];
    }

    /**
     * @return list<string>
     */
    private function violatedPaths(SalesChannelProductAssignmentRequest $request): array
    {
        $paths = [];
        foreach ($this->validator->validate($request) as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        return $paths;
    }

    /**
     * @return list<string>
     */
    private function ids(int $count): array
    {
        return array_map(static fn (int $i): string => Uuid::fromStringToHex('id-' . $i), range(1, $count));
    }
}
