<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Order\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\TestBrowser;

/**
 * @internal
 */
class OrderActionControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use IntegrationTestBehaviour;

    private TestBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = $this->createClient();
    }

    /**
     * @param string[] $privileges
     */
    #[DataProvider('aclTestCasesProvider')]
    public function testRoutesAreSecuredProperly(string $entity, array $privileges, bool $shouldSucceed): void
    {
        $this->authorizeBrowser($this->browser, aclPermissions: $privileges);

        $this->browser->request('POST', \sprintf('/api/_action/%s/00000000000000000000000000000000/state/paid', $entity));
        $response = $this->browser->getResponse();

        if ($shouldSucceed) {
            // The route should be accessible, but since the entity with the given ID does not exist for this test,
            // we expect a 400 Bad Request response instead of a 403 Forbidden
            static::assertSame(400, $response->getStatusCode());

            return;
        }

        static::assertSame(403, $response->getStatusCode());
    }

    /**
     * @return \Generator<string, array{entity: string, privileges: string[], shouldSucceed: bool}>
     */
    public static function aclTestCasesProvider(): \Generator
    {
        yield 'order state transition with privileges' => [
            'entity' => 'order',
            'privileges' => ['order:update'],
            'shouldSucceed' => true,
        ];

        yield 'order state transition with wrong privileges' => [
            'entity' => 'order',
            'privileges' => ['order:create', 'order:read'],
            'shouldSucceed' => false,
        ];

        yield 'order transaction state transition with privileges' => [
            'entity' => 'order_transaction',
            'privileges' => ['order_transaction:update'],
            'shouldSucceed' => true,
        ];

        yield 'order transaction state transition with wrong privileges' => [
            'entity' => 'order_transaction',
            'privileges' => ['order_transaction:create', 'order_transaction:read'],
            'shouldSucceed' => false,
        ];

        yield 'order delivery state transition with privileges' => [
            'entity' => 'order_delivery',
            'privileges' => ['order_delivery:update'],
            'shouldSucceed' => true,
        ];

        yield 'order delivery state transition with wrong privileges' => [
            'entity' => 'order_delivery',
            'privileges' => ['order_delivery:create', 'order_delivery:read'],
            'shouldSucceed' => false,
        ];
    }
}
