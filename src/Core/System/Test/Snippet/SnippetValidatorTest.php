<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Snippet;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Files\SnippetFileCollection;
use Shopware\Core\System\Snippet\SnippetFileHandler;
use Shopware\Core\System\Snippet\SnippetValidator;

/**
 * @internal
 */
#[Package('system-settings')]
class SnippetValidatorTest extends TestCase
{
    public function testValidateShouldFindMissingSnippets(): void
    {
        $snippetFileHandler = $this->getMockBuilder(SnippetFileHandler::class)
            ->disableOriginalConstructor()
            ->getMock();

        $firstPath = 'irrelevant.de-DE.json';
        $secondPath = 'irrelevant.en-GB.json';
        $snippetFileHandler->method('findAdministrationSnippetFiles')
            ->willReturn([$firstPath]);
        $snippetFileHandler->method('findStorefrontSnippetFiles')
            ->willReturn([$secondPath]);

        $snippetFileHandler->method('openJsonFile')
            ->willReturnCallback(function ($path) use ($firstPath) {
                if ($path === $firstPath) {
                    return ['german' => 'exampleGerman'];
                }

                return ['english' => 'exampleEnglish'];
            });

        $snippetValidator = new SnippetValidator(new SnippetFileCollection(), $snippetFileHandler, '');
        $missingSnippets = $snippetValidator->validate();

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
        $snippetFileHandler->method('findAdministrationSnippetFiles')
            ->willReturn([$firstPath]);
        $snippetFileHandler->method('findStorefrontSnippetFiles')
            ->willReturn([$secondPath]);

        $snippetFileHandler->method('openJsonFile')
            ->willReturnCallback(fn () => ['foo' => 'bar']);

        $snippetValidator = new SnippetValidator(new SnippetFileCollection(), $snippetFileHandler, '');
        $missingSnippets = $snippetValidator->validate();

        static::assertCount(0, $missingSnippets);
    }
}
