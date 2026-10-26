<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Script;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\AppSystemTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class AppContextCreatorTest extends TestCase
{
    use AdminApiTestBehaviour;
    use AppSystemTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testWriteWithoutPrivilegeIsForbidden(): void
    {
        $app = $this->installFixtureApp();
        $connection = static::getContainer()->get(Connection::class);
        $browser = $this->getBrowserAuthenticatedWithIntegration($app->getIntegrationId());

        $taxId = Uuid::randomHex();
        $browser->jsonRequest('POST', '/api/script/write-without-privilege', [
            'taxId' => $taxId,
            'taxName' => 'without-privilege',
        ]);

        static::assertSame(
            Response::HTTP_FORBIDDEN,
            $browser->getResponse()->getStatusCode(),
            'tax write must be forbidden without tax:create: ' . (string) $browser->getResponse()->getContent()
        );
        static::assertSame(0, $this->countTax($taxId, $connection));
    }

    /**
     * @param array<string, string> $payload
     */
    #[DataProvider('contextChangeScriptProvider')]
    public function testScriptCannotChangeARequestContext(string $script, array $payload): void
    {
        $app = $this->installFixtureApp();
        $connection = static::getContainer()->get(Connection::class);
        $browser = $this->getBrowserAuthenticatedWithIntegration($app->getIntegrationId());

        $taxId = Uuid::randomHex();
        $browser->jsonRequest('POST', '/api/script/' . $script, [...$payload, 'taxId' => $taxId]);

        $content = (string) $browser->getResponse()->getContent();
        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode(), $content);
        static::assertSame(AdapterException::TWIG_METHOD_CALL_NOT_ALLOWED, \json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['errors'][0]['code']);
        static::assertSame(0, $this->countTax($taxId, $connection));
    }

    public static function contextChangeScriptProvider(): \Generator
    {
        yield 'source of a search result' => ['changed-search-context', []];
        yield 'source of a write result' => ['changed-write-context', ['tagId' => Uuid::randomHex()]];
        yield 'scope switch with an arrow function' => ['scope-switch', []];
    }

    private function installFixtureApp(): AppEntity
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'context-isolation-app'));
        /** @var AppEntity|null $app */
        $app = static::getContainer()->get('app.repository')
            ->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();
        static::assertInstanceOf(AppEntity::class, $app, 'fixture app must be installed');

        $connection = static::getContainer()->get(Connection::class);
        $integrationIsAdmin = $connection->fetchOne(
            'SELECT `admin` FROM `integration` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($app->getIntegrationId())]
        );
        static::assertSame(0, (int) $integrationIsAdmin, 'the app integration must not be an admin');

        return $app;
    }

    private function countTax(string $taxId, Connection $connection): int
    {
        return (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM `tax` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($taxId)]
        );
    }
}
