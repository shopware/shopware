<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Administration;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Files\SnippetFileCollection;
use Shopware\Core\System\Snippet\SnippetFileHandler;
use Shopware\Core\System\Snippet\SnippetValidator;
use Shopware\Storefront\Storefront;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SnippetValidator::class)]
class SnippetValidatorTest extends TestCase
{
    public function testValidateShouldFindMissingSnippets(): void
    {
        $snippetFileHandler = $this->getMockBuilder(SnippetFileHandler::class)
            ->disableOriginalConstructor()
            ->getMock();

        $firstPath = 'irrelevant.de-DE.json';
        $secondPath = 'irrelevant.en-GB.json';

        $matcher = static::exactly(2);
        $snippetFileHandler->expects($matcher)
            ->method('findBundleSnippetFiles')
            ->willReturnOnConsecutiveCalls()
            ->willReturnCallback(function () use ($matcher, $firstPath, $secondPath) {
                return match ($matcher->numberOfInvocations()) {
                    1 => [$firstPath],
                    2 => [$secondPath],
                    default => null,
                };
            });

        $snippetFileHandler->method('openJsonFile')
            ->willReturnCallback(function ($path) use ($firstPath) {
                if ($path === $firstPath) {
                    return ['german' => 'exampleGerman'];
                }

                return ['english' => 'exampleEnglish'];
            });

        $matcher = static::exactly(2);
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->expects($matcher)
            ->method('getBundle')
            ->willReturnOnConsecutiveCalls()
            ->willReturnCallback(function () use ($matcher) {
                return match ($matcher->numberOfInvocations()) {
                    1 => new Storefront(),
                    2 => new Administration(),
                    default => null,
                };
            })
        ;

        $snippetValidator = new SnippetValidator(new SnippetFileCollection(), $snippetFileHandler, '', $kernel);
        $missingSnippets = $snippetValidator->validate(['Storefront', 'Administration']);

        static::assertCount(2, $missingSnippets);
        static::assertArrayHasKey('german', $missingSnippets['en-GB']);
        static::assertSame('german', $missingSnippets['en-GB']['german']['keyPath']);
        static::assertSame('exampleGerman', $missingSnippets['en-GB']['german']['availableValue']);

        static::assertArrayHasKey('english', $missingSnippets['de-DE']);
        static::assertSame('english', $missingSnippets['de-DE']['english']['keyPath']);
        static::assertSame('exampleEnglish', $missingSnippets['de-DE']['english']['availableValue']);
    }

    public function testValidateShouldNotFindAnyMissingSnippets(): void
    {
        $snippetFileHandler = $this->getMockBuilder(SnippetFileHandler::class)
            ->disableOriginalConstructor()
            ->getMock();

        $firstPath = 'irrelevant.de-DE.json';
        $secondPath = 'irrelevant.en-GB.json';
        $snippetFileHandler->method('findBundleSnippetFiles')
            ->with(new Storefront())
            ->willReturn([$firstPath, $secondPath]);

        $snippetFileHandler->method('openJsonFile')
            ->willReturnCallback(fn () => ['foo' => 'bar']);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->expects(static::once())
            ->method('getBundle')
            ->with('Storefront')
            ->willReturn(new Storefront());

        $snippetValidator = new SnippetValidator(new SnippetFileCollection(), $snippetFileHandler, '', $kernel);
        $missingSnippets = $snippetValidator->validate(['Storefront']);

        static::assertCount(0, $missingSnippets);
    }
}
