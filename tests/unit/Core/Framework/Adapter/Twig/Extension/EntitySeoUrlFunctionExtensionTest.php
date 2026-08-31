<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Twig\Extension\EntitySeoUrlFunctionExtension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Twig\TwigFunction;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntitySeoUrlFunctionExtension::class)]
class EntitySeoUrlFunctionExtensionTest extends TestCase
{
    private EntityRouteResolver&MockObject $entityRouteResolver;

    private EntitySeoUrlFunctionExtension $extension;

    protected function setUp(): void
    {
        $this->entityRouteResolver = $this->createMock(EntityRouteResolver::class);

        $this->extension = new EntitySeoUrlFunctionExtension($this->entityRouteResolver);
    }

    public function testGetFunctionsExposesEntitySeoUrl(): void
    {
        $this->entityRouteResolver->expects($this->never())->method('generateSeoUrlPlaceholder');

        $functions = $this->extension->getFunctions();

        static::assertCount(1, $functions);
        static::assertInstanceOf(TwigFunction::class, $functions[0]);
        static::assertSame('entitySeoUrl', $functions[0]->getName());
        static::assertTrue($functions[0]->needsContext());
    }

    public function testForwardsNullSalesChannelTypeIdWhenContextIsMissing(): void
    {
        $primaryKey = Uuid::randomHex();

        $this->entityRouteResolver
            ->expects($this->once())
            ->method('generateSeoUrlPlaceholder')
            ->with('product', $primaryKey, null)
            ->willReturn('entity-url');

        static::assertSame('entity-url', $this->extension->entitySeoUrl([], 'product', $primaryKey));
    }

    public function testForwardsSalesChannelTypeIdFromContext(): void
    {
        $primaryKey = Uuid::randomHex();

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());
        $salesChannel->setTypeId(Defaults::SALES_CHANNEL_TYPE_API);

        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);

        $this->entityRouteResolver
            ->expects($this->once())
            ->method('generateSeoUrlPlaceholder')
            ->with('product', $primaryKey, Defaults::SALES_CHANNEL_TYPE_API)
            ->willReturn('headless-url');

        static::assertSame(
            'headless-url',
            $this->extension->entitySeoUrl(['salesChannelContext' => $salesChannelContext], 'product', $primaryKey)
        );
    }
}
