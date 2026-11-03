<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Snippet;

use League\OAuth2\Server\Exception\OAuthServerException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Snippet\AdministrationSnippetService;
use Shopware\Administration\Snippet\SnippetFinderInterface;
use Shopware\Core\Framework\Api\OAuth\SymfonyBearerTokenValidator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(AdministrationSnippetService::class)]
class AdministrationSnippetServiceTest extends TestCase
{
    public function testGetSnippetsDefaultsToFallbackLocale(): void
    {
        $snippetFinder = $this->createMock(SnippetFinderInterface::class);
        $snippetFinder
            ->expects($this->once())
            ->method('findSnippets')
            ->with('en-GB')
            ->willReturn(['global' => ['title' => 'Title']]);

        $service = new AdministrationSnippetService($snippetFinder, static::createStub(SymfonyBearerTokenValidator::class));

        static::assertSame(['en-GB' => ['global' => ['title' => 'Title']]], $service->getSnippets(new Request()));
    }

    public function testGetSnippetsAddsFallbackLocaleWhenLocaleIsDifferent(): void
    {
        $snippetFinder = $this->createMock(SnippetFinderInterface::class);
        $snippetFinder
            ->expects($this->exactly(2))
            ->method('findSnippets')
            ->willReturnCallback(static fn (string $locale): array => ['global' => [$locale => 'x']]);

        $service = new AdministrationSnippetService($snippetFinder, static::createStub(SymfonyBearerTokenValidator::class));

        static::assertSame(
            [
                'de-DE' => ['global' => ['de-DE' => 'x']],
                'en-GB' => ['global' => ['en-GB' => 'x']],
            ],
            $service->getSnippets(new Request(query: ['locale' => 'de-DE']))
        );
    }

    public function testGetSnippetsRemovesKeysFromFallbackWhichAreAlreadyProvidedByRequestedLocale(): void
    {
        $snippetFinder = $this->createMock(SnippetFinderInterface::class);
        $snippetFinder
            ->expects($this->exactly(2))
            ->method('findSnippets')
            ->willReturnCallback(static fn (string $locale): array => match ($locale) {
                'de-DE' => [
                    'global' => ['title' => 'Titel', 'nested' => ['a' => 'A-de']],
                    'sw-login' => ['hello' => 'Hallo'],
                ],
                default => [
                    'global' => ['title' => 'Title', 'nested' => ['a' => 'A-en', 'b' => 'B-en'], 'only' => 'Only-en'],
                    'sw-login' => ['hello' => 'Hello'],
                    'sw-product' => ['name' => 'Name'],
                ],
            });

        $service = new AdministrationSnippetService($snippetFinder, static::createStub(SymfonyBearerTokenValidator::class));

        static::assertSame(
            [
                'de-DE' => [
                    'global' => ['title' => 'Titel', 'nested' => ['a' => 'A-de']],
                    'sw-login' => ['hello' => 'Hallo'],
                ],
                'en-GB' => [
                    'global' => ['nested' => ['b' => 'B-en'], 'only' => 'Only-en'],
                    'sw-product' => ['name' => 'Name'],
                ],
            ],
            $service->getSnippets(new Request(query: ['locale' => 'de-DE']))
        );
    }

    public function testGetSnippetsKeepsFallbackValueWhenStructureDiffersFromRequestedLocale(): void
    {
        $snippetFinder = $this->createMock(SnippetFinderInterface::class);
        $snippetFinder
            ->expects($this->exactly(2))
            ->method('findSnippets')
            ->willReturnCallback(static fn (string $locale): array => match ($locale) {
                'de-DE' => ['global' => ['a' => 'scalar', 'b' => ['c' => 'nested']]],
                default => ['global' => ['a' => ['x' => 'array'], 'b' => 'scalar']],
            });

        $service = new AdministrationSnippetService($snippetFinder, static::createStub(SymfonyBearerTokenValidator::class));

        $snippets = $service->getSnippets(new Request(query: ['locale' => 'de-DE']));

        static::assertSame(['global' => ['a' => ['x' => 'array'], 'b' => 'scalar']], $snippets['en-GB']);
    }

    public function testGetSnippetsOmitsLocalesWithoutRemainingSnippets(): void
    {
        $snippetFinder = $this->createMock(SnippetFinderInterface::class);
        $snippetFinder
            ->expects($this->exactly(2))
            ->method('findSnippets')
            ->willReturn(['global' => ['title' => 'Title']]);

        $service = new AdministrationSnippetService($snippetFinder, static::createStub(SymfonyBearerTokenValidator::class));

        static::assertSame(
            ['de-DE' => ['global' => ['title' => 'Title']]],
            $service->getSnippets(new Request(query: ['locale' => 'de-DE']))
        );
    }

    public function testGetSnippetsFiltersNamespacesForUnauthenticatedRequests(): void
    {
        $snippetFinder = $this->createMock(SnippetFinderInterface::class);
        $snippetFinder
            ->expects($this->exactly(2))
            ->method('findSnippets')
            ->willReturnCallback(static fn (string $locale): array => [
                'global' => [$locale => 'x'],
                'sw-login' => [$locale => 'y'],
                'sw-product' => [$locale => 'z'],
            ]);

        $tokenValidator = $this->createMock(SymfonyBearerTokenValidator::class);
        $tokenValidator
            ->expects($this->once())
            ->method('validateAuthorization')
            ->willThrowException(new OAuthServerException('', 0, ''));

        $service = new AdministrationSnippetService($snippetFinder, $tokenValidator);

        $snippets = $service->getSnippets(new Request(query: ['locale' => 'de-DE']));

        static::assertSame(['global', 'sw-login'], \array_keys($snippets['de-DE']));
        static::assertSame(['global', 'sw-login'], \array_keys($snippets['en-GB']));
    }

    public function testGetSnippetsKeepsAllNamespacesForAuthenticatedRequests(): void
    {
        $snippetFinder = static::createStub(SnippetFinderInterface::class);
        $snippetFinder
            ->method('findSnippets')
            ->willReturn(['global' => ['a' => 'x'], 'sw-product' => ['b' => 'y']]);

        $tokenValidator = $this->createMock(SymfonyBearerTokenValidator::class);
        $tokenValidator
            ->expects($this->once())
            ->method('validateAuthorization');

        $service = new AdministrationSnippetService($snippetFinder, $tokenValidator);

        $snippets = $service->getSnippets(new Request());

        static::assertSame(['global', 'sw-product'], \array_keys($snippets['en-GB']));
    }
}
