<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\Extension\SalesChannelCmsPageLoaderExtension;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Examples\SalesChannelCmsPageLoaderExample;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(SalesChannelCmsPageLoaderExtension::class)]
#[CoversClass(SalesChannelCmsPageLoaderExample::class)]
class SalesChannelCmsPageLoaderExtensionTest extends TestCase
{
    public function testSubscriberResolvesPageLoad(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SalesChannelCmsPageLoaderExample());

        $coreCalled = false;
        $result = (new ExtensionDispatcher($dispatcher))->publish(
            name: SalesChannelCmsPageLoaderExtension::NAME,
            extension: new SalesChannelCmsPageLoaderExtension(new Request(), new Criteria(), $context),
            function: static function () use (&$coreCalled): EntitySearchResult {
                $coreCalled = true;

                return new EntitySearchResult(
                    CmsPageDefinition::ENTITY_NAME,
                    1,
                    new CmsPageCollection([(new CmsPageEntity())->assign(['id' => 'core-page'])]),
                    null,
                    new Criteria(),
                    Context::createDefaultContext(),
                );
            },
        );

        static::assertFalse($coreCalled, 'The core CMS page loader must be skipped when a subscriber resolves it.');
        static::assertInstanceOf(EntitySearchResult::class, $result);
        static::assertSame(['example-page'], array_values($result->getEntities()->getIds()));
    }
}
