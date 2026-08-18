<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\Snippet\Api;

use GuzzleHttp\Psr7\Response;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetCollection;
use Shopware\Core\System\Snippet\Service\AbstractTranslationLoader;
use Shopware\Tests\Integration\Core\System\Snippet\TranslationClientBehaviour;

/**
 * @internal
 */
#[Package('discovery')]
class TranslationControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use TranslationClientBehaviour;

    /**
     * Pseudo-locale: installing it auto-creates the locale, language and snippet set, so the test
     * needs no pre-existing locale fixture.
     */
    private const LOCALE = 'ach-UG';

    #[After]
    public function cleanupTranslationFilesystem(): void
    {
        $filesystem = static::getContainer()->get('shopware.filesystem.private');
        static::assertInstanceOf(Filesystem::class, $filesystem);

        if ($filesystem->directoryExists(AbstractTranslationLoader::TRANSLATION_DIR)) {
            $filesystem->deleteDirectory(AbstractTranslationLoader::TRANSLATION_DIR);
        }
    }

    public function testList(): void
    {
        $browser = $this->getBrowser();
        $browser->jsonRequest('GET', '/api/_action/translation/list');

        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode());

        $content = $this->decodeResponse($response->getContent());
        static::assertArrayHasKey('total', $content);
        static::assertArrayHasKey('items', $content);
        static::assertNotEmpty($content['items']);
        static::assertCount($content['total'], $content['items']);

        $item = $content['items'][0];
        static::assertArrayHasKey('locale', $item);
        static::assertArrayHasKey('name', $item);
        static::assertArrayHasKey('lastUpdate', $item);
        static::assertArrayHasKey('progress', $item);
        static::assertArrayHasKey('updateAvailable', $item);
        static::assertArrayHasKey('isPseudoLanguage', $item);
        // meta moved to its own endpoint and must no longer be part of the list response
        static::assertArrayNotHasKey('meta', $content);
    }

    public function testMeta(): void
    {
        $browser = $this->getBrowser();
        $browser->jsonRequest('GET', '/api/_action/translation/meta');

        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode());

        $content = $this->decodeResponse($response->getContent());
        static::assertArrayHasKey('builtInLocales', $content);
        static::assertArrayHasKey('communityTranslationsUrl', $content);
        static::assertArrayHasKey('documentationUrlSnippetKey', $content);
        static::assertArrayHasKey('completenessThreshold', $content);
    }

    public function testInstall(): void
    {
        $this->mockTranslationDownload();

        $browser = $this->getBrowser();
        $browser->jsonRequest('POST', '/api/_action/translation/install', ['locales' => [self::LOCALE]]);

        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode());

        $content = $this->decodeResponse($response->getContent());
        static::assertSame([self::LOCALE], $content['updated']);
        static::assertSame([], $content['skipped']);

        static::assertSame(1, $this->countBaseSnippetSets(self::LOCALE));
    }

    public function testUpdate(): void
    {
        // No locales are installed locally, so update short-circuits and makes no remote request at all.
        $browser = $this->getBrowser();
        $browser->jsonRequest('POST', '/api/_action/translation/update');

        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode());

        $content = $this->decodeResponse($response->getContent());
        static::assertSame([], $content['updated']);
        static::assertSame([], $content['skipped']);
    }

    public function testUpdateRefreshesInstalledLocale(): void
    {
        // Install the locale first, then queue a newer metadata entry plus its file downloads so update refreshes it.
        $this->mockTranslationDownload();

        $browser = $this->getBrowser();
        $browser->jsonRequest('POST', '/api/_action/translation/install', ['locales' => [self::LOCALE]]);
        static::assertSame(200, $browser->getResponse()->getStatusCode());

        $this->appendTranslationResponse($this->metadataResponse('2025-06-01T00:00:00+00:00'));
        $this->appendTranslationFileResponses();

        $browser->jsonRequest('POST', '/api/_action/translation/update');

        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode());

        $content = $this->decodeResponse($response->getContent());
        static::assertSame([self::LOCALE], $content['updated']);
        static::assertSame([], $content['skipped']);
    }

    public function testDelete(): void
    {
        $this->mockTranslationDownload();

        $browser = $this->getBrowser();
        $browser->jsonRequest('POST', '/api/_action/translation/install', ['locales' => [self::LOCALE]]);
        static::assertSame(200, $browser->getResponse()->getStatusCode());

        $filesystem = static::getContainer()->get('shopware.filesystem.private');
        static::assertInstanceOf(Filesystem::class, $filesystem);
        static::assertTrue($filesystem->directoryExists('translation/locale/' . self::LOCALE));

        $browser->jsonRequest('DELETE', '/api/_action/translation/' . self::LOCALE);

        $response = $browser->getResponse();
        static::assertSame(204, $response->getStatusCode());
        static::assertFalse($filesystem->directoryExists('translation/locale/' . self::LOCALE));
    }

    public function testEndpointsAreForbiddenWithoutTranslationPrivileges(): void
    {
        $browser = $this->getBrowser(true, [], []);

        $browser->jsonRequest('GET', '/api/_action/translation/list');
        static::assertSame(403, $browser->getResponse()->getStatusCode());

        $browser->jsonRequest('POST', '/api/_action/translation/install', ['locales' => ['xx-XX']]);
        static::assertSame(403, $browser->getResponse()->getStatusCode());

        $browser->jsonRequest('POST', '/api/_action/translation/update');
        static::assertSame(403, $browser->getResponse()->getStatusCode());

        $browser->jsonRequest('DELETE', '/api/_action/translation/xx-XX');
        static::assertSame(403, $browser->getResponse()->getStatusCode());
    }

    public function testListAllowedWithReadPrivilege(): void
    {
        $browser = $this->getBrowser(true, [], ['system:translation:read']);

        $browser->jsonRequest('GET', '/api/_action/translation/list');

        static::assertSame(200, $browser->getResponse()->getStatusCode());
    }

    public function testInstallAllowedWithCreatePrivilege(): void
    {
        $browser = $this->getBrowser(true, [], ['system:translation:create']);

        // ACL passes; the invalid locale then fails validation (400), proving the privilege granted access.
        $browser->jsonRequest('POST', '/api/_action/translation/install', ['locales' => ['xx-XX']]);

        static::assertSame(400, $browser->getResponse()->getStatusCode());
    }

    public function testUpdateAllowedWithUpdatePrivilege(): void
    {
        // Nothing is installed, so update short-circuits without a remote request; this only asserts ACL access.
        $browser = $this->getBrowser(true, [], ['system:translation:update']);

        $browser->jsonRequest('POST', '/api/_action/translation/update');

        static::assertSame(200, $browser->getResponse()->getStatusCode());
    }

    public function testDeleteAllowedWithDeletePrivilege(): void
    {
        $browser = $this->getBrowser(true, [], ['system:translation:delete']);

        // ACL passes; the invalid locale then fails validation (400), proving the privilege granted access.
        $browser->jsonRequest('DELETE', '/api/_action/translation/xx-XX');

        static::assertSame(400, $browser->getResponse()->getStatusCode());
    }

    /**
     * Queues the remote responses for a full install: the metadata lookup followed by one empty
     * snippet-file response per configured bundle and plugin.
     */
    private function mockTranslationDownload(): void
    {
        $this->appendTranslationResponse($this->metadataResponse());
        $this->appendTranslationFileResponses();
    }

    private function metadataResponse(string $updatedAt = '2025-01-01T00:00:00+00:00'): Response
    {
        $body = json_encode([
            ['locale' => self::LOCALE, 'updatedAt' => $updatedAt, 'progress' => 100],
        ], \JSON_THROW_ON_ERROR);

        return new Response(200, [], $body);
    }

    private function countBaseSnippetSets(string $locale): int
    {
        /** @var EntityRepository<SnippetSetCollection> $repository */
        $repository = static::getContainer()->get('snippet_set.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('iso', $locale));
        $criteria->addFilter(new EqualsFilter('name', "BASE $locale"));

        return $repository->searchIds($criteria, Context::createDefaultContext())->getTotal();
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(string|false $content): array
    {
        static::assertIsString($content);

        $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);

        return $decoded;
    }
}
