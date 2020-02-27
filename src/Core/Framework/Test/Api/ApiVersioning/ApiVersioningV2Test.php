<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiVersioning;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\ApiConverter\ConverterV2;
use Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v2\BundleDefinition;
use Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Migrations\Migration1571753490v1;
use Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Migrations\Migration1571754409v2;
use Shopware\Core\Framework\Test\Api\ApiVersioning\Tests\ApiVersioningTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class ApiVersioningV2Test extends TestCase
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
            1 => [],
            2 => [new ConverterV2()],
        ]);

        static::registerDefinition(BundleDefinition::class);

        static::runMigrations(
            [
                new Migration1571753490v1(),
            ],
            [
                new Migration1571754409v2(),
            ]
        );
    }

    public static function tearDownAfterClass(): void
    {
        // clear manipulated internal state of services
        static::clearCache();

        $connection = static::container()->get(Connection::class);
        $connection->executeUpdate('DROP TABLE IF EXISTS `_test_bundle`');
    }

    public function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->browser = $this->getBrowser();
    }

    public function testCreateV1Works(): void
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
                'longDescription' => 'long description',
            ])
        );

        static::assertEquals(204, $this->browser->getResponse()->getStatusCode(), print_r($this->browser->getResponse()->getContent(), true));

        $result = $this->connection->fetchAll('SELECT * FROM _test_bundle');
        static::assertCount(1, $result);
        static::assertEquals('absolute', $result[0]['discount_type']);
        static::assertEquals(1, $result[0]['is_absolute']);
        static::assertEquals('test description', $result[0]['description']);
        static::assertEquals('long description', $result[0]['long_description']);
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

        $result = $this->connection->fetchAll('SELECT * FROM _test_bundle');
        static::assertCount(1, $result);
        static::assertEquals('absolute', $result[0]['discount_type']);
        static::assertEquals(1, $result[0]['is_absolute']);
        static::assertEquals('test description', $result[0]['description']);
        static::assertNull($result[0]['long_description']);
    }

    public function testCreateV3ReturnsNotFound(): void
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
            ])
        );

        static::assertEquals(404, $this->browser->getResponse()->getStatusCode(), print_r($this->browser->getResponse()->getContent(), true));
    }
}
