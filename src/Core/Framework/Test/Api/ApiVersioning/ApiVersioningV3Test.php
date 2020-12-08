<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiVersioning;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\ApiConverter\ConverterV2;
use Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\ApiConverter\ConverterV3;
use Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v3\Aggregate\BundlePrice\BundlePriceDefinition;
use Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v3\Aggregate\BundleTanslation\BundleTranslationDefinition;
use Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v3\BundleDefinition;
use Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Migrations\Migration1571753490v1;
use Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Migrations\Migration1571754409v2;
use Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Migrations\Migration1571832058v3;
use Shopware\Core\Framework\Test\Api\ApiVersioning\Tests\ApiVersioningTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @group slow
 */
class ApiVersioningV3Test extends TestCase
{
    use IntegrationTestBehaviour;
    use AdminApiTestBehaviour;
    use ApiVersioningTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var KernelBrowser
     */
    private $browser;

    public static function setUpBeforeClass(): void
    {
        // clear the cached routes
        static::clearCache();

        static::setApiVersions([
            2 => [new ConverterV2()],
            3 => [new ConverterV3()],
        ]);

        static::registerDefinition(
            BundleDefinition::class,
            BundleTranslationDefinition::class,
            BundlePriceDefinition::class
        );

        static::runMigrations(
            [
                new Migration1571753490v1(),
                new Migration1571754409v2(),
            ],
            [
                new Migration1571832058v3(),
            ]
        );
    }

    public static function tearDownAfterClass(): void
    {
        // clear manipulated internal state of services
        static::clearCache();

        $connection = static::container()->get(Connection::class);
        $connection->executeUpdate('DROP TABLE IF EXISTS `_test_bundle_translation`');
        $connection->executeUpdate('DROP TABLE IF EXISTS `_test_bundle_price`');
        $connection->executeUpdate('DROP TABLE IF EXISTS `_test_bundle`');
    }

    public function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->browser = $this->getBrowser();
    }

    public function testCreateV1ReturnsNotFound(): void
    {
        $this->browser->request(
            'POST',
            '/api/v1/-test-bundle',
            [],
            [],
            [],
            json_encode([
                'name' => 'test_bundle',
                'discountType' => 'absolute',
                'discount' => 5.5,
                'description' => 'test description',
            ])
        );

        static::assertEquals(404, $this->browser->getResponse()->getStatusCode(), print_r($this->browser->getResponse()->getContent(), true));
    }

    public function testCreateV2Works(): void
    {
        $this->browser->request(
            'POST',
            '/api/v2/-test-bundle',
            [],
            [],
            [],
            json_encode([
                'name' => 'test_bundle',
                'isAbsolute' => true,
                'discount' => 5.5,
                'description' => 'test description',
            ])
        );

        static::assertEquals(204, $this->browser->getResponse()->getStatusCode(), print_r($this->browser->getResponse()->getContent(), true));

        $bundles = $this->connection->fetchAll('SELECT * FROM _test_bundle');
        static::assertCount(1, $bundles);
        static::assertEquals('test_bundle', $bundles[0]['name']);
        static::assertEquals('test description', $bundles[0]['description']);

        $translations = $this->connection->fetchAll('SELECT * FROM _test_bundle_translation');
        static::assertCount(1, $translations);
        static::assertEquals($bundles[0]['id'], $translations[0]['_test_bundle_id']);
        static::assertEquals(Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), $translations[0]['language_id']);
        static::assertEquals('test_bundle', $translations[0]['name']);
        static::assertEquals('test description', $translations[0]['translated_description']);
    }

    public function testCreateV3Works(): void
    {
        $this->browser->request(
            'POST',
            '/api/v3/-test-bundle',
            [],
            [],
            [],
            json_encode([
                'name' => 'test_bundle',
                'isAbsolute' => true,
                'discount' => 5.5,
                'translatedDescription' => 'test description',
                'pseudoPrice' => 10,
                'prices' => [
                    [
                        'quantityStart' => 0,
                        'price' => [[
                            'currencyId' => Defaults::CURRENCY,
                            'gross' => 10.19,
                            'net' => 10,
                            'linked' => true,
                        ]],
                    ],
                ],
            ])
        );

        static::assertEquals(204, $this->browser->getResponse()->getStatusCode(), print_r($this->browser->getResponse()->getContent(), true));

        $bundles = $this->connection->fetchAll('SELECT * FROM _test_bundle');
        static::assertCount(1, $bundles);
        static::assertEquals('test_bundle', $bundles[0]['name']);
        static::assertEquals('test description', $bundles[0]['description']);
        static::assertEquals(10, $bundles[0]['pseudo_price']);

        $translations = $this->connection->fetchAll('SELECT * FROM _test_bundle_translation');
        static::assertCount(1, $translations);
        static::assertEquals($bundles[0]['id'], $translations[0]['_test_bundle_id']);
        static::assertEquals(Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), $translations[0]['language_id']);
        static::assertEquals('test_bundle', $translations[0]['name']);
        static::assertEquals('test description', $translations[0]['translated_description']);

        $prices = $this->connection->fetchAll('SELECT * FROM _test_bundle_price');
        static::assertCount(1, $prices);
        static::assertEquals($bundles[0]['id'], $prices[0]['bundle_id']);
        static::assertEquals(0, $prices[0]['quantity_start']);
    }
}
