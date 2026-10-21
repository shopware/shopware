<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Script;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
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
use Shopware\Core\Test\TestDefaults;
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

    public function testChangedWriteContextIsNotReused(): void
    {
        $app = $this->installFixtureApp();
        $connection = static::getContainer()->get(Connection::class);

        $userId = Uuid::randomHex();
        $this->createNonAdminUser($userId, $connection);
        $passwordBefore = $this->readUserPassword($userId, $connection);
        static::assertNotSame('', $passwordBefore);

        $taxId = Uuid::randomHex();
        $browser = $this->getBrowserAuthenticatedWithIntegration($app->getIntegrationId());
        $browser->jsonRequest('POST', '/api/script/reused-write-context', [
            'taxId' => $taxId,
            'taxName' => 'reused-context',
            'systemEntity' => 'user',
            'systemPayload' => [
                'id' => $userId,
                'admin' => true,
            ],
        ]);

        static::assertSame(
            Response::HTTP_FORBIDDEN,
            $browser->getResponse()->getStatusCode(),
            'write with a changed context must be forbidden: ' . (string) $browser->getResponse()->getContent()
        );

        static::assertSame(0, $this->countTax($taxId, $connection), 'no tax may be written');
        static::assertSame(0, $this->readUserAdmin($userId, $connection), 'user must stay non-admin');
        static::assertSame($passwordBefore, $this->readUserPassword($userId, $connection));
    }

    public function testConsecutiveScriptRunsStartFromOriginalPrivileges(): void
    {
        $app = $this->installFixtureApp();
        $connection = static::getContainer()->get(Connection::class);
        $browser = $this->getBrowserAuthenticatedWithIntegration($app->getIntegrationId());

        // A changed source must not carry over into the next run of the same app.
        $firstTaxId = Uuid::randomHex();
        $browser->jsonRequest('POST', '/api/script/reused-write-context', [
            'taxId' => $firstTaxId,
            'taxName' => 'first-run',
            'systemEntity' => 'user',
            'systemPayload' => ['id' => Uuid::randomHex(), 'admin' => true],
        ]);
        static::assertSame(
            Response::HTTP_FORBIDDEN,
            $browser->getResponse()->getStatusCode(),
            (string) $browser->getResponse()->getContent()
        );

        $secondTaxId = Uuid::randomHex();
        $browser->jsonRequest('POST', '/api/script/reused-write-context', [
            'taxId' => $secondTaxId,
            'taxName' => 'second-run',
            'systemEntity' => 'user',
            'systemPayload' => ['id' => Uuid::randomHex(), 'admin' => true],
        ]);
        static::assertSame(
            Response::HTTP_FORBIDDEN,
            $browser->getResponse()->getStatusCode(),
            'second run must also start from the original non-admin privileges: ' . (string) $browser->getResponse()->getContent()
        );
        static::assertSame(0, $this->countTax($secondTaxId, $connection));
    }

    public function testChangedSearchContextIsNotReused(): void
    {
        $app = $this->installFixtureApp();
        $browser = $this->getBrowserAuthenticatedWithIntegration($app->getIntegrationId());

        $browser->jsonRequest('POST', '/api/script/reused-search-context', []);

        static::assertSame(
            Response::HTTP_OK,
            $browser->getResponse()->getStatusCode(),
            (string) $browser->getResponse()->getContent()
        );
        $content = $browser->getResponse()->getContent();
        static::assertNotFalse($content);
        $response = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($response);

        // The change stays on its own object; a later search gets a new, non-admin source.
        static::assertTrue($response['firstAdmin'], 'the changed source keeps its own change');
        static::assertFalse($response['secondAdmin'], 'a later search must get a new, non-admin source');
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

    private function createNonAdminUser(string $userId, Connection $connection): void
    {
        static::getContainer()->get('user.repository')->create([[
            'id' => $userId,
            'email' => $userId . '@example.com',
            'firstName' => 'Regular',
            'lastName' => 'User',
            'username' => 'user-' . $userId,
            'password' => TestDefaults::HASHED_PASSWORD,
            'localeId' => $this->getSystemLocaleId($connection),
        ]], Context::createDefaultContext());

        static::assertSame(0, $this->readUserAdmin($userId, $connection), 'user starts as non-admin');
    }

    private function getSystemLocaleId(Connection $connection): string
    {
        return (string) $connection->fetchOne(
            'SELECT LOWER(HEX(locale.id))
             FROM language
             INNER JOIN locale ON language.locale_id = locale.id
             WHERE language.id = :id',
            ['id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );
    }

    private function readUserAdmin(string $userId, Connection $connection): int
    {
        return (int) $connection->fetchOne(
            'SELECT `admin` FROM `user` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($userId)]
        );
    }

    private function readUserPassword(string $userId, Connection $connection): string
    {
        return (string) $connection->fetchOne(
            'SELECT `password` FROM `user` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($userId)]
        );
    }

    private function countTax(string $taxId, Connection $connection): int
    {
        return (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM `tax` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($taxId)]
        );
    }
}
