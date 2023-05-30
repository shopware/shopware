<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Seo;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateDefinition;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;

/**
 * @internal
 */
class SeoUrlTemplateRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testCreate(): void
    {
        $id = Uuid::randomHex();
        $template = [
            'id' => $id,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'routeName' => ProductPageSeoUrlRoute::ROUTE_NAME,
            'entityName' => $this->getContainer()->get(ProductDefinition::class)->getEntityName(),
            'template' => ProductPageSeoUrlRoute::DEFAULT_TEMPLATE,
        ];

        $context = Context::createDefaultContext();
        /** @var EntityRepository $repo */
        $repo = $this->getContainer()->get('seo_url_template.repository');
        $events = $repo->create([$template], $context);
        static::assertCount(1, $events->getEvents());

        $event = $events->getEventByEntityName(SeoUrlTemplateDefinition::ENTITY_NAME);
        static::assertNotNull($event);
        static::assertCount(1, $event->getPayloads());
    }

    /**
     * @dataProvider templateUpdateDataProvider
     */
    public function testUpdate(string $id, array $template): void
    {
        $context = Context::createDefaultContext();
        /** @var EntityRepository $repo */
        $repo = $this->getContainer()->get('seo_url_template.repository');
        $repo->create([$template], $context);

        $update = [
            'id' => $id,
            'routeName' => 'foo_bar',
        ];
        $events = $repo->update([$update], $context);
        $event = $events->getEventByEntityName(SeoUrlTemplateDefinition::ENTITY_NAME);
        static::assertNotNull($event);
        static::assertCount(1, $event->getPayloads());

        /** @var SeoUrlTemplateEntity $first */
        $first = $repo->search(new Criteria([$id]), $context)->first();
        static::assertEquals($update['id'], $first->getId());
        static::assertEquals($update['routeName'], $first->getRouteName());
    }

    public function testDelete(): void
    {
        $id = Uuid::randomHex();
        $template = [
            'id' => $id,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'routeName' => ProductPageSeoUrlRoute::ROUTE_NAME,
            'entityName' => $this->getContainer()->get(ProductDefinition::class)->getEntityName(),
            'template' => ProductPageSeoUrlRoute::DEFAULT_TEMPLATE,
        ];

        $context = Context::createDefaultContext();
        /** @var EntityRepository $repo */
        $repo = $this->getContainer()->get('seo_url_template.repository');
        $repo->create([$template], $context);

        $result = $repo->delete([['id' => $id]], $context);
        $event = $result->getEventByEntityName(SeoUrlTemplateDefinition::ENTITY_NAME);
        static::assertEquals([$id], $event->getIds());

        /** @var SeoUrlTemplateEntity|null $first */
        $first = $repo->search(new Criteria([$id]), $context)->first();
        static::assertNull($first);
    }

    public static function templateUpdateDataProvider(): iterable
    {
        $templates = [
            [
                'id' => null,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'routeName' => ProductPageSeoUrlRoute::ROUTE_NAME,
                'entityName' => ProductDefinition::ENTITY_NAME,
                'template' => ProductPageSeoUrlRoute::DEFAULT_TEMPLATE,
            ],
            [
                'id' => null,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'routeName' => ProductPageSeoUrlRoute::ROUTE_NAME,
                'entityName' => ProductDefinition::ENTITY_NAME,
                'template' => '',
            ],
        ];

        foreach ($templates as $template) {
            $id = Uuid::randomHex();
            $template['id'] = $id;

            yield [
                'id' => $id,
                'template' => $template,
            ];
        }
    }
}
