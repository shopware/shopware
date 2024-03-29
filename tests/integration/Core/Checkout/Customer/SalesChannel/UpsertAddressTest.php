<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Salutation\SalutationDefinition;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Package('checkout')]
#[Group('store-api')]
class UpsertAddressTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private Connection $connection;

    protected function setUp(): void
    {
        $ids = new TestDataCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $ids->create('sales-channel'),
        ]);

        $this->connection = $this->getContainer()->get(Connection::class);

        $this->assignSalesChannelContext($this->browser);

        $email = Uuid::randomHex() . '@example.com';
        $this->createCustomer($email);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => 'shopware',
                ]
            );

        $response = $this->browser->getResponse();

        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);
    }

    public function testUpsertAddressWithExistingNotSpecifiedSalutation(): void
    {
        $data = [
            'firstName' => 'Test',
            'lastName' => 'Test',
            'street' => 'Test',
            'city' => 'Test',
            'zipcode' => 'Test',
            'countryId' => $this->getValidCountryId(),
        ];

        $salutations = $this->connection->fetchAllKeyValue('SELECT salutation_key, id FROM salutation');
        static::assertArrayHasKey(SalutationDefinition::NOT_SPECIFIED, $salutations);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/address',
                $data
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertNotNull($response['salutationId']);
    }

    public function testUpsertAddressToNotSpecifiedWithoutExistingSalutation(): void
    {
        $data = [
            'firstName' => 'Test',
            'lastName' => 'Test',
            'street' => 'Test',
            'city' => 'Test',
            'zipcode' => 'Test',
            'countryId' => $this->getValidCountryId(),
        ];

        $this->connection->executeStatement(
            '
					DELETE FROM salutation WHERE salutation_key = :salutationKey
				',
            ['salutationKey' => SalutationDefinition::NOT_SPECIFIED]
        );

        $salutations = $this->connection->fetchAllKeyValue('SELECT salutation_key, id FROM salutation');
        static::assertArrayNotHasKey(SalutationDefinition::NOT_SPECIFIED, $salutations);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/address',
                $data
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertNull($response['salutationId']);
    }
}
