<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\EntityProtection;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\PluginDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\EntityProtection\_fixtures\PluginProtectionExtension;
use Shopware\Core\Framework\Test\DataAbstractionLayer\EntityProtection\_fixtures\SystemConfigExtension;
use Shopware\Core\Framework\Test\DataAbstractionLayer\EntityProtection\_fixtures\UserAccessKeyExtension;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SystemConfig\SystemConfigDefinition;
use Shopware\Core\System\User\Aggregate\UserAccessKey\UserAccessKeyDefinition;

class EntityProtectionValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AdminApiTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    public function setUp(): void
    {
        $this->registerDefinitionWithExtensions(PluginDefinition::class, PluginProtectionExtension::class);
        $this->registerDefinitionWithExtensions(SystemConfigDefinition::class, SystemConfigExtension::class);
        $this->registerDefinitionWithExtensions(UserAccessKeyDefinition::class, UserAccessKeyExtension::class);
    }

    public function tearDown(): void
    {
        $this->removeExtension(
            PluginProtectionExtension::class,
            SystemConfigExtension::class,
            UserAccessKeyExtension::class
        );
    }

    /**
     * @dataProvider blockedApiRequest
     * @group slow
     */
    public function testItBlocksApiAccess(string $method, string $url): void
    {
        $this->getBrowser()
            ->request(
                $method,
                '/api/v' . PlatformRequest::API_VERSION . '/' . $url
            );

        $response = $this->getBrowser()->getResponse();

        static::assertEquals(403, $response->getStatusCode(), $response->getContent());
    }

    public function blockedApiRequest(): array
    {
        return [
            ['GET', 'plugin/' . Uuid::randomHex()], // detail
            ['GET', 'plugin'], // list
            ['POST', 'plugin'], // create
            ['PATCH', 'plugin/' . Uuid::randomHex()], // update
            ['DELETE', 'plugin/' . Uuid::randomHex()], // delete
            ['POST', 'search/plugin'], // search
            ['POST', 'search-ids/plugin'], // search ids

            // nested routes
            ['POST', 'search/user/' . Uuid::randomHex() . '/access-keys'], // search
            ['POST', 'search-ids/user/' . Uuid::randomHex() . '/access-keys'], // search ids
        ];
    }

    public function testItAllowsReadsOnEntitiesWithWriteProtectionOnly(): void
    {
        $this->getBrowser()
            ->request(
                'GET',
                '/api/v' . PlatformRequest::API_VERSION . '/system-config'
            );

        $response = $this->getBrowser()->getResponse();

        static::assertNotEquals(403, $response->getStatusCode(), $response->getContent());

        $this->getBrowser()
            ->request(
                'GET',
                '/api/v' . PlatformRequest::API_VERSION . '/system-config/' . Uuid::randomHex()
            );

        $response = $this->getBrowser()->getResponse();

        static::assertNotEquals(403, $response->getStatusCode(), $response->getContent());

        $this->getBrowser()
            ->request(
                'POST',
                '/api/v' . PlatformRequest::API_VERSION . '/system-config'
            );

        $response = $this->getBrowser()->getResponse();

        static::assertEquals(403, $response->getStatusCode(), $response->getContent());
    }

    public function testItBlocksReadsOnForbiddenAssociations(): void
    {
        $this->getBrowser()
            ->request(
                'POST',
                '/api/v' . PlatformRequest::API_VERSION . '/search/user',
                [
                    'associations' => [
                        'accessKeys' => [],
                    ],
                ]
            );

        $response = $this->getBrowser()->getResponse();

        static::assertEquals(403, $response->getStatusCode(), $response->getContent());

        $this->getBrowser()
            ->request(
                'POST',
                '/api/v' . PlatformRequest::API_VERSION . '/search/user',
                [
                    'associations' => [
                        'avatarMedia' => [],
                    ],
                ]
            );

        $response = $this->getBrowser()->getResponse();

        static::assertNotEquals(403, $response->getStatusCode(), $response->getContent());
    }

    public function testItBlocksReadsOnForbiddenNestedAssociations(): void
    {
        $this->getBrowser()
            ->request(
                'POST',
                '/api/v' . PlatformRequest::API_VERSION . '/search/media',
                [
                    'associations' => [
                        'user' => [
                            'associations' => [
                                'accessKeys' => [],
                            ],
                        ],
                    ],
                ]
            );

        $response = $this->getBrowser()->getResponse();

        static::assertEquals(403, $response->getStatusCode(), $response->getContent());

        $this->getBrowser()
            ->request(
                'POST',
                '/api/v' . PlatformRequest::API_VERSION . '/search/media',
                [
                    'associations' => [
                        'user' => [
                            'associations' => [
                                'avatarMedia' => [],
                            ],
                        ],
                    ],
                ]
            );

        $response = $this->getBrowser()->getResponse();

        static::assertNotEquals(403, $response->getStatusCode(), $response->getContent());
    }

    public function testItDoesNotValidateCascadeDeletes(): void
    {
        // system_config has a cascade delete on sales_channel
        $this->getBrowser()
            ->request(
                'DELETE',
                '/api/v' . PlatformRequest::API_VERSION . '/sales-channel/' . Defaults::SALES_CHANNEL
            );

        $response = $this->getBrowser()->getResponse();

        static::assertEquals(204, $response->getStatusCode(), $response->getContent());

        /** @var EntityRepositoryInterface $salesChannelRepository */
        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');

        static::assertEquals(
            1,
            $salesChannelRepository->search(new Criteria(), Context::createDefaultContext())->getTotal()
        );
    }
}
