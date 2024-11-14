<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\Events\CmsPageLoadedEvent;
use Shopware\Core\Content\Cms\Subscriber\CmsLinksForStoreApiSubscriber;
use Shopware\Core\Content\Media\MediaUrlPlaceholderHandlerInterface;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Api\Context\ShopApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(CmsLinksForStoreApiSubscriber::class)]
class CmsLinksForStoreApiSubscriberTest extends TestCase
{
    private CmsLinksForStoreApiSubscriber $subscriber;

    protected function setUp(): void
    {
        $seoUrlReplacer = $this->createMock(SeoUrlPlaceholderHandlerInterface::class);
        $seoUrlReplacer->method('replace')->willReturnCallback(function (string $content) {
            return $content;
        });
        $mediaUrlReplacer = $this->createMock(MediaUrlPlaceholderHandlerInterface::class);
        $mediaUrlReplacer->method('replace')->willReturnCallback(function (string $content) {
            if (str_contains($content, $this->getOriginalStaticContent())) {
                return $this->getTransformedContent();
            }

            return $content;
        });

        $this->subscriber = new CmsLinksForStoreApiSubscriber(
            $seoUrlReplacer,
            $mediaUrlReplacer
        );
    }

    public function testGetSubscribedEvents(): void
    {
        $events = CmsLinksForStoreApiSubscriber::getSubscribedEvents();
        static::assertArrayHasKey(CmsPageLoadedEvent::class, $events);
        static::assertEquals('relativeLinksForStoreAPIOutput', $events[CmsPageLoadedEvent::class]);
    }

    public function testRelativeLinksForStoreAPIOutput(): void
    {
        $request = new Request();
        $request->server->set('REQUEST_URI', '/store-api/category/0192245eafa37a74b72aa75db0146f2e');
        $request->attributes->set('_route', 'store-api.category.detail');
        $request->attributes->set('_routeScope', ['store-api']);
        $context = new Context(new ShopApiSource('test'));
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $pages = $this->getCmsPagesWithTestData();
        $event = $this->createMock(CmsPageLoadedEvent::class);
        $event->method('getRequest')->willReturn($request);
        $event->method('getResult')->willReturn($pages);
        $event->method('getSalesChannelContext')->willReturn($salesChannelContext);
        $event->method('getContext')->willReturn($context);

        $this->subscriber->relativeLinksForStoreAPIOutput($event);
        $firstPage = $pages->first();

        static::assertCount(2, $pages);
        static::assertSame($this->getTransformedContent(), $firstPage?->getSections()?->first()?->getBlocks()?->first()?->getSlots()?->first()?->getTranslated()['config']['content']['value']);
        static::assertEquals(new ArrayStruct(
            [
                'content' => [
                    'value' => $this->getOriginalStaticContent(),
                    'source' => 'static',
                ],
            ]
        ), $firstPage->getSections()->first()->getBlocks()->first()->getSlots()->first()->getData());
    }

    private function getOriginalStaticContent(): string
    {
        return '<h2>This page content is testing links in CMS</h2><p>- <a target=\"_self\" href=\"124c71d524604ccbad6042edce3ac799/navigation/01920590471d70e59cdd73d38223d192#\" rel=\"noreferrer noopener\">Category Link</a></p><p>- <a target=\"_self\" href=\"124c71d524604ccbad6042edce3ac799/detail/0192059054e1713ebbb66d1c13cf90ab#\" rel=\"noreferrer noopener\">Product Link</a></p><p>- <a target=\"_self\" href=\"124c71d524604ccbad6042edce3ac799/mediaId/0192058ec7c971ea8069a1f3ba7b3f76#\" rel=\"noreferrer noopener\">File Link</a></p><p>- <a target=\"_self\" href=\"mailto:b.meyer@shopware.com\" rel=\"noreferrer noopener\">E-Mail</a></p><p>- <a target=\"_self\" href=\"tel:+49725133445566\" rel=\"noreferrer noopener\">Phone Number</a></p>';
    }

    private function getTransformedContent(): string
    {
        return '<h2>This page content is testing links in CMS</h2><p>- <a target=\"_self\" href=\"/Garden/Games/\">Category Link</a></p><p>- <a target=\"_self\" href=\"/Enormous-Plastic-Bedder/SW-0192059054e1713ebbb66d1c14858de1\">Product Link</a></p><p>- <a target=\"_self\" href=\"/media/e9/96/12/1726670096/demostore-logo.png?ts=1726670096\">File Link</a></p><p>- <a target=\"_self\" href=\"mailto:b.meyer@shopware.com\">E-Mail</a></p><p>- <a target=\"_self\" href=\"tel:+49725133445566\">Phone Number</a></p>';
    }

    private function getCmsPagesWithTestData(): CmsPageCollection
    {
        $cmsPage1 = (new CmsPageEntity())->assign([
            'id' => 'page-1',
            'sections' => new CmsSectionCollection([
                (new CmsSectionEntity())->assign([
                    'id' => 'section-1',
                    'position' => 2,
                    'blocks' => new CmsBlockCollection([
                        (new CmsBlockEntity())->assign([
                            'id' => 'block-1',
                            'position' => 3,
                            'slots' => new CmsSlotCollection([
                                (new CmsSlotEntity())->assign([
                                    'id' => 'slot-1',
                                    'slot' => 'left',
                                    'type' => 'text',
                                    'config' => [
                                        'content' => [
                                            'value' => $this->getOriginalStaticContent(),
                                            'source' => 'static',
                                        ]],
                                    'translated' => [
                                        'config' => [
                                            'content' => [
                                                'value' => $this->getOriginalStaticContent(),
                                                'source' => 'static',
                                            ],
                                        ],
                                    ],
                                    'data' => new ArrayStruct(
                                        [
                                            'content' => [
                                                'value' => $this->getOriginalStaticContent(),
                                                'source' => 'static',
                                            ],
                                        ]
                                    ),
                                ]),
                                (new CmsSlotEntity())->assign([
                                    'id' => 'slot-2',
                                    'slot' => 'right',
                                    'type' => 'foo',
                                    'translated' => [
                                        'config' => ['Config'],
                                    ],
                                ]),
                                (new CmsSlotEntity())->assign([
                                    'id' => 'slot-3',
                                    'slot' => 'content',
                                    'type' => 'foo',
                                ]),
                            ]),
                        ]),
                        (new CmsBlockEntity())->assign([
                            'id' => 'block-2',
                            'position' => 1,
                            'slots' => new CmsSlotCollection([
                                (new CmsSlotEntity())->assign([
                                    'id' => 'slot-1',
                                    'slot' => 'content',
                                    'type' => 'foo',
                                    'config' => ['translated' => '0'],
                                ]),
                            ]),
                        ]),
                        (new CmsBlockEntity())->assign([
                            'id' => 'block-3',
                            'position' => 2,
                        ]),
                    ]),
                ]),
                (new CmsSectionEntity())->assign([
                    'id' => 'section-2',
                    'position' => 1,
                ]),
            ]),
        ]);

        $cmsPage2 = (new CmsPageEntity())->assign([
            'id' => 'page-2',
        ]);

        return new CmsPageCollection([$cmsPage1, $cmsPage2]);
    }
}
