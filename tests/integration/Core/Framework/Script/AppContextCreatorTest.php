<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Script;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
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
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
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

    public function testChangedSearchContextIsNotReusedByALaterWrite(): void
    {
        $browser = $this->getBrowserAuthenticatedWithIntegration($this->installFixtureApp()->getIntegrationId());

        $response = $this->requestScript($browser, 'reused-search-context-write', ['tagId' => Uuid::randomHex()]);

        static::assertTrue($response['changedAdmin'], 'the changed search context keeps its own change');
        static::assertFalse($response['writeAdmin'], 'a later write must get a new, non-admin source');
        static::assertSame(Context::USER_SCOPE, $response['writeScope']);
    }

    public function testChangedWriteContextIsNotReusedByALaterWrite(): void
    {
        $browser = $this->getBrowserAuthenticatedWithIntegration($this->installFixtureApp()->getIntegrationId());

        $response = $this->requestScript($browser, 'reused-write-context', [
            'firstTagId' => Uuid::randomHex(),
            'secondTagId' => Uuid::randomHex(),
        ]);

        static::assertTrue($response['changedAdmin'], 'the changed write context keeps its own change');
        static::assertFalse($response['writeAdmin'], 'a later write must get a new, non-admin source');
        static::assertSame(Context::USER_SCOPE, $response['writeScope'], 'a later write must not inherit the system scope');
    }

    /**
     * @param array<string, string> $payload
     */
    #[DataProvider('changedContextScriptProvider')]
    public function testDeniedWriteAfterAChangedContextIsForbidden(string $script, array $payload): void
    {
        $browser = $this->getBrowserAuthenticatedWithIntegration($this->installFixtureApp()->getIntegrationId());

        $browser->jsonRequest('POST', '/api/script/' . $script, [...$payload, 'taxId' => Uuid::randomHex()]);

        static::assertSame(
            Response::HTTP_FORBIDDEN,
            $browser->getResponse()->getStatusCode(),
            'tax write must stay forbidden after a changed context: ' . (string) $browser->getResponse()->getContent()
        );
    }

    public static function changedContextScriptProvider(): \Generator
    {
        yield 'changed search context' => ['reused-search-context-write', ['tagId' => Uuid::randomHex()]];
        yield 'changed write context' => ['reused-write-context', ['firstTagId' => Uuid::randomHex(), 'secondTagId' => Uuid::randomHex()]];
    }

    public function testConsecutiveScriptRunsStartFromOriginalPrivileges(): void
    {
        $browser = $this->getBrowserAuthenticatedWithIntegration($this->installFixtureApp()->getIntegrationId());

        // A changed source must not carry over into the next run of the same app.
        $this->requestScript($browser, 'reused-write-context', [
            'firstTagId' => Uuid::randomHex(),
            'secondTagId' => Uuid::randomHex(),
        ]);
        $response = $this->requestScript($browser, 'reused-write-context', [
            'firstTagId' => Uuid::randomHex(),
            'secondTagId' => Uuid::randomHex(),
        ]);

        static::assertFalse($response['initialAdmin'], 'second run must start from the original non-admin privileges');
    }

    public function testChangedSearchContextIsNotReused(): void
    {
        $browser = $this->getBrowserAuthenticatedWithIntegration($this->installFixtureApp()->getIntegrationId());

        $response = $this->requestScript($browser, 'reused-search-context', []);

        // The change stays on its own object; a later search gets a new, non-admin source.
        static::assertTrue($response['firstAdmin'], 'the changed source keeps its own change');
        static::assertFalse($response['secondAdmin'], 'a later search must get a new, non-admin source');
    }

    public function testChangedContextsDoNotReachTheAclService(): void
    {
        $browser = $this->getBrowserAuthenticatedWithIntegration($this->installFixtureApp()->getIntegrationId());

        $response = $this->requestScript($browser, 'acl-after-changed-context', ['tagId' => Uuid::randomHex()]);

        static::assertFalse($response['canBefore']);
        static::assertFalse($response['canAfter'], 'acl must not see a change made to a returned context');
        static::assertTrue($response['canGranted'], 'acl must still grant the app privileges');
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

    /**
     * @param array<string, string> $payload
     *
     * @return array<string, mixed>
     */
    private function requestScript(KernelBrowser $browser, string $script, array $payload): array
    {
        $browser->jsonRequest('POST', '/api/script/' . $script, $payload);

        static::assertSame(
            Response::HTTP_OK,
            $browser->getResponse()->getStatusCode(),
            (string) $browser->getResponse()->getContent()
        );
        $content = $browser->getResponse()->getContent();
        static::assertNotFalse($content);
        $response = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($response);

        return $response;
    }

    private function countTax(string $taxId, Connection $connection): int
    {
        return (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM `tax` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($taxId)]
        );
    }
}
