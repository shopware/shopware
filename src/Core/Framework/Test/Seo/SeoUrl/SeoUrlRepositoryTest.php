<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Seo\SeoUrl;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
class SeoUrlRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testCreate(): void
    {
        $id = Uuid::randomHex();
        $fk = Uuid::randomHex();
        $url = [
            'id' => $id,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'foreignKey' => $fk,

            'routeName' => 'testRoute',
            'pathInfo' => '/ugly/path',
            'seoPathInfo' => '/pretty/path',

            'isCanonical' => true,
            'isModified' => false,
        ];

        $context = Context::createDefaultContext();
        /** @var EntityRepository $repo */
        $repo = $this->getContainer()->get('seo_url.repository');
        $events = $repo->create([$url], $context);
        static::assertCount(1, $events->getEvents());

        $event = $events->getEventByEntityName(SeoUrlDefinition::ENTITY_NAME);
        static::assertNotNull($event);
        static::assertCount(1, $event->getPayloads());
    }

    public function testUpdate(): void
    {
        $id = Uuid::randomHex();
        $fk = Uuid::randomHex();
        $url = [
            'id' => $id,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'foreignKey' => $fk,

            'routeName' => 'testRoute',
            'pathInfo' => '/ugly/path',
            'seoPathInfo' => '/pretty/path',

            'isCanonical' => true,
            'isModified' => false,
        ];

        $context = Context::createDefaultContext();
        /** @var EntityRepository $repo */
        $repo = $this->getContainer()->get('seo_url.repository');
        $repo->create([$url], $context);

        $update = [
            'id' => $id,
            'seoPathInfo' => '/even/prettier/path',
        ];
        $events = $repo->update([$update], $context);
        $event = $events->getEventByEntityName(SeoUrlDefinition::ENTITY_NAME);
        static::assertNotNull($event);
        static::assertCount(1, $event->getPayloads());

        /** @var SeoUrlEntity $first */
        $first = $repo->search(new Criteria([$id]), $context)->first();
        static::assertEquals($update['id'], $first->getId());
        static::assertEquals($update['seoPathInfo'], $first->getSeoPathInfo());
    }

    public function testDelete(): void
    {
        $id = Uuid::randomHex();
        $fk = Uuid::randomHex();
        $url = [
            'id' => $id,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'foreignKey' => $fk,

            'routeName' => 'testRoute',
            'pathInfo' => '/ugly/path',
            'seoPathInfo' => '/pretty/path',

            'isCanonical' => true,
            'isModified' => false,
        ];

        $context = Context::createDefaultContext();
        /** @var EntityRepository $repo */
        $repo = $this->getContainer()->get('seo_url.repository');
        $repo->create([$url], $context);

        $result = $repo->delete([['id' => $id]], $context);
        $event = $result->getEventByEntityName(SeoUrlDefinition::ENTITY_NAME);
        static::assertEquals([$id], $event->getIds());

        /** @var SeoUrlEntity|null $first */
        $first = $repo->search(new Criteria([$id]), $context)->first();
        static::assertNull($first);
    }

    public function testEmptySeoUrlCollection(): void
    {
        $registry = new SeoUrlRouteRegistry($this->emptyGenerator());
        static::assertSame([], (array) $registry->getSeoUrlRoutes());

        $registry = new SeoUrlRouteRegistry([]);
        static::assertSame([], (array) $registry->getSeoUrlRoutes());
    }

    private function emptyGenerator(): \Generator
    {
        yield from [];
    }
}
