<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Adapter\AbstractSpecificationSource;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\ContentSystem\SpecificationData;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AbstractSpecificationSource::class)]
class AbstractSpecificationSourceTest extends TestCase
{
    #[TestDox('a source that does not opt in is not selectable for any entity type')]
    public function testSupportsEntityTypeDefaultsToFalse(): void
    {
        $source = $this->createNonEntitySource();

        static::assertFalse($source->supportsEntityType('product'));
    }

    #[TestDox('resolving entity specification data without opting in throws unknownEntityType')]
    public function testResolveSpecificationDataForEntityDefaultThrows(): void
    {
        $source = $this->createNonEntitySource();

        $this->expectExceptionObject(ContentSystemException::unknownEntityType('prod-1'));

        $source->resolveSpecificationDataForEntity('prod-1', new Request(), Generator::generateSalesChannelContext());
    }

    private function createNonEntitySource(): AbstractSpecificationSource
    {
        return new class extends AbstractSpecificationSource {
            public function supports(string $path, Request $request, SalesChannelContext $context): bool
            {
                return false;
            }

            public function resolveLayoutId(string $path, Request $request, SalesChannelContext $context): string
            {
                return '';
            }

            public function resolveSpecificationData(string $path, Request $request, SalesChannelContext $context): SpecificationData
            {
                return new SpecificationData([], PlaceholderValues::from([]));
            }

            public function resolveTargetElementId(string $path, Request $request, SalesChannelContext $context): ?string
            {
                return null;
            }

            /**
             * @return list<string>
             */
            public function resolveCacheTags(string $path, Request $request, SalesChannelContext $context): array
            {
                return [];
            }
        };
    }
}
