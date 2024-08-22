<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Seo\SeoUrl;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
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

    /**
     * @var EntityRepository<SeoUrlCollection>
     */
    private EntityRepository $seoUrlRepository;

    protected function setUp(): void
    {
        $this->seoUrlRepository = $this->getContainer()->get('seo_url.repository');
    }

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
        $events = $this->seoUrlRepository->create([$url], $context);
        static::assertNotNull($events->getEvents());
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
        $this->seoUrlRepository->create([$url], $context);

        $update = [
            'id' => $id,
            'seoPathInfo' => '/even/prettier/path',
        ];
        $events = $this->seoUrlRepository->update([$update], $context);
        $event = $events->getEventByEntityName(SeoUrlDefinition::ENTITY_NAME);
        static::assertNotNull($event);
        static::assertCount(1, $event->getPayloads());

        $first = $this->seoUrlRepository->search(new Criteria([$id]), $context)
            ->getEntities()
            ->first();
        static::assertInstanceOf(SeoUrlEntity::class, $first);
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
        $this->seoUrlRepository->create([$url], $context);

        $result = $this->seoUrlRepository->delete([['id' => $id]], $context);
        $event = $result->getEventByEntityName(SeoUrlDefinition::ENTITY_NAME);
        static::assertNotNull($event);
        static::assertEquals([$id], $event->getIds());

        $first = $this->seoUrlRepository->search(new Criteria([$id]), $context)->first();
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
