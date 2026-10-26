<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Notification\McpListVersions;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Integration\IntegrationCollection;
use Shopware\Core\System\User\UserCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * An edited MCP allowlist changes what a connected client may see, so every path that writes it raises the
 * versions of the lists whose entry changed.
 *
 * @internal
 */
#[Package('framework')]
class McpAllowlistListChangedTest extends TestCase
{
    use AdminApiTestBehaviour;
    use IntegrationTestBehaviour;

    private const BEFORE = ['tools' => ['shopware-order-search'], 'resources' => null, 'prompts' => null];

    /**
     * The entity and Sync API writes of a user need a freshly verified admin login (`user-verified` scope),
     * so for users the repository write stands in for them: it is the DAL write both end in.
     *
     * @return iterable<string, array{'integration'|'user', 'action'|'entity'|'sync'|'repository'}>
     */
    public static function writePathProvider(): iterable
    {
        yield 'integration allowlist action' => ['integration', 'action'];
        yield 'integration entity API' => ['integration', 'entity'];
        yield 'integration Sync API' => ['integration', 'sync'];
        yield 'integration repository' => ['integration', 'repository'];
        yield 'user allowlist action' => ['user', 'action'];
        yield 'user repository' => ['user', 'repository'];
    }

    /**
     * @param 'integration'|'user' $entity
     * @param 'action'|'entity'|'sync'|'repository' $path
     */
    #[DataProvider('writePathProvider')]
    public function testAnAllowlistEditRaisesTheVersionsOfTheChangedLists(string $entity, string $path): void
    {
        $id = $this->createPrincipal($entity);
        $before = $this->versions();

        $this->writeAllowlist($entity, $path, $id, ['tools' => ['shopware-product-search'], 'resources' => null, 'prompts' => ['shopware-context']]);

        static::assertSame(
            ['tools' => $before['tools'] + 1, 'resources' => $before['resources'], 'prompts' => $before['prompts'] + 1],
            $this->versions(),
        );
    }

    /**
     * @param 'integration'|'user' $entity
     * @param 'action'|'entity'|'sync'|'repository' $path
     */
    #[DataProvider('writePathProvider')]
    public function testRemovingTheAllowlistRaisesTheVersionsOfTheRestrictedLists(string $entity, string $path): void
    {
        $id = $this->createPrincipal($entity);
        $before = $this->versions();

        $this->writeAllowlist($entity, $path, $id, null);

        static::assertSame(
            ['tools' => $before['tools'] + 1, 'resources' => $before['resources'], 'prompts' => $before['prompts']],
            $this->versions(),
        );
    }

    public function testWritingTheSameAllowlistAgainRaisesNothing(): void
    {
        $id = $this->createPrincipal('integration');
        $before = $this->versions();

        $this->writeAllowlist('integration', 'entity', $id, ['prompts' => null, 'resources' => null, 'tools' => ['shopware-order-search']]);

        static::assertSame($before, $this->versions());
    }

    public function testAWriteWithoutTheAllowlistRaisesNothing(): void
    {
        $id = $this->createPrincipal('integration');
        $before = $this->versions();

        $this->getBrowser()->jsonRequest('PATCH', '/api/integration/' . $id, ['label' => 'renamed']);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());

        static::assertSame($before, $this->versions());
    }

    /**
     * @param 'integration'|'user' $entity
     */
    private function createPrincipal(string $entity): string
    {
        $id = Uuid::randomHex();
        $context = Context::createDefaultContext();

        if ($entity === 'integration') {
            /** @var EntityRepository<IntegrationCollection> $repository */
            $repository = static::getContainer()->get('integration.repository');
            $repository->create([[
                'id' => $id,
                'label' => 'mcp allowlist test',
                'accessKey' => 'SWIA' . strtoupper(Uuid::randomHex()),
                'secretAccessKey' => 'secret' . Uuid::randomHex(),
                'mcpAllowlist' => self::BEFORE,
            ]], $context);

            return $id;
        }

        /** @var EntityRepository<UserCollection> $repository */
        $repository = static::getContainer()->get('user.repository');
        $repository->create([[
            'id' => $id,
            'localeId' => $this->getLocaleIdOfSystemLanguage(),
            'username' => 'mcp-' . $id,
            'password' => 'shopware-password',
            'firstName' => 'Mcp',
            'lastName' => 'Allowlist',
            'email' => 'mcp-' . $id . '@example.com',
            'mcpAllowlist' => self::BEFORE,
        ]], $context);

        return $id;
    }

    /**
     * @param 'integration'|'user' $entity
     * @param 'action'|'entity'|'sync'|'repository' $path
     * @param array<string, list<string>|null>|null $allowlist
     */
    private function writeAllowlist(string $entity, string $path, string $id, ?array $allowlist): void
    {
        if ($path === 'repository') {
            $repository = static::getContainer()->get($entity . '.repository');
            static::assertInstanceOf(EntityRepository::class, $repository);
            $repository->update([['id' => $id, 'mcpAllowlist' => $allowlist]], Context::createDefaultContext());

            return;
        }

        $browser = $this->getBrowser();

        match ($path) {
            'action' => $browser->jsonRequest('POST', \sprintf('/api/_action/%s/%s/mcp-allowlist', $entity, $id), ['allowlist' => $allowlist]),
            'entity' => $browser->jsonRequest('PATCH', \sprintf('/api/%s/%s', $entity, $id), ['mcpAllowlist' => $allowlist]),
            'sync' => $browser->jsonRequest('POST', '/api/_action/sync', [[
                'key' => 'allowlist',
                'action' => 'upsert',
                'entity' => $entity,
                'payload' => [['id' => $id, 'mcpAllowlist' => $allowlist]],
            ]]),
        };

        $response = $browser->getResponse();
        static::assertLessThan(300, $response->getStatusCode(), (string) $response->getContent());
    }

    /**
     * @return array{tools: int, resources: int, prompts: int}
     */
    private function versions(): array
    {
        return static::getContainer()->get(McpListVersions::class)->current();
    }

    private function getLocaleIdOfSystemLanguage(): string
    {
        $localeId = static::getContainer()->get('language.repository')
            ->search(new Criteria([Defaults::LANGUAGE_SYSTEM]), Context::createDefaultContext())
            ->first()
            ?->get('localeId');
        static::assertIsString($localeId);

        return $localeId;
    }
}
