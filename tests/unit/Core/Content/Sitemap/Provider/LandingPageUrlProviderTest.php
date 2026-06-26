<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Sitemap\Provider;

use Doctrine\DBAL\Cache\ArrayResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Shopware\Core\Content\Sitemap\Provider\LandingPageUrlProvider;
use Shopware\Core\Content\Sitemap\Service\ConfigHandler;
use Shopware\Core\Content\Sitemap\Struct\Url;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(LandingPageUrlProvider::class)]
class LandingPageUrlProviderTest extends TestCase
{
    private readonly ConfigHandler&MockObject $configHandler;

    private readonly Connection&MockObject $connection;

    private readonly EntityRouteResolver&MockObject $entityRouteResolver;

    private readonly EventDispatcher&MockObject $dispatcher;

    private LandingPageUrlProvider $landingPageUrlProvider;

    protected function setUp(): void
    {
        $this->configHandler = $this->createMock(ConfigHandler::class);
        $this->connection = $this->createMock(Connection::class);
        $this->entityRouteResolver = $this->createMock(EntityRouteResolver::class);
        $this->dispatcher = $this->createMock(EventDispatcher::class);

        $this->landingPageUrlProvider = new LandingPageUrlProvider(
            $this->configHandler,
            $this->connection,
            $this->entityRouteResolver,
            $this->dispatcher
        );
    }

    public function testGetDecorated(): void
    {
        static::expectException(DecorationPatternException::class);
        $this->landingPageUrlProvider->getDecorated();
    }

    public function testGetName(): void
    {
        $name = $this->landingPageUrlProvider->getName();
        static::assertSame('landing_page', $name);
    }

    public function testGetLandingPageUrls(): void
    {
        $ids = new IdsCollection();
        $queryResult = new Result(
            new ArrayResult(
                ['id', 'created_at', 'updated_at'],
                [
                    [Uuid::fromHexToBytes($ids->get('landing-page-1')), '2021-01-01 00:00:00', null],
                    [Uuid::fromHexToBytes($ids->get('landing-page-2')), '2021-01-01 00:00:00', null],
                ]
            ),
            $this->connection
        );

        $this->connection->method('fetchAllAssociative')->willReturn([
            [
                'foreign_key' => $ids->get('landing-page-1'),
                'seo_path_info' => 'landing-page/1/detail',
            ],
        ]);

        $this->entityRouteResolver->method('getRouteNameForEntityName')->willReturn('frontend.landing.page');
        $this->entityRouteResolver->method('generateUrl')->willReturn('landing-page/2/detail');

        $queryBuilderMock = $this->createMock(QueryBuilder::class);
        $queryBuilderMock->method('executeQuery')->willReturn($queryResult);

        $this->connection->method('createQueryBuilder')->willReturn($queryBuilderMock);

        $this->configHandler->method('get')
            ->willReturn([
                [
                    'resource' => LandingPageEntity::class,
                    'salesChannelId' => TestDefaults::SALES_CHANNEL,
                    'identifier' => $ids->get('landing-page-1'),
                ],
                [
                    'resource' => LandingPageEntity::class,
                    'salesChannelId' => Uuid::randomHex(),
                    'identifier' => $ids->get('landing-page-2'),
                ],
            ]);

        $context = Generator::generateSalesChannelContext();

        $urlResult = $this->landingPageUrlProvider->getUrls($context, 2, 10);

        $urls = $urlResult->getUrls();
        static::assertCount(2, $urls);

        $url = array_shift($urls);
        static::assertInstanceOf(Url::class, $url);
        static::assertSame($ids->get('landing-page-1'), $url->getIdentifier());
        static::assertSame('landing-page/1/detail', $url->getLoc());

        $url = array_shift($urls);
        static::assertInstanceOf(Url::class, $url);
        static::assertSame($ids->get('landing-page-2'), $url->getIdentifier());
        static::assertSame('landing-page/2/detail', $url->getLoc());

        static::assertSame(12, $urlResult->getNextOffset());
    }
}
