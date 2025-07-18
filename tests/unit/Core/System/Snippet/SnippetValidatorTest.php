<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Files\SnippetFileCollection;
use Shopware\Core\System\Snippet\SnippetFileHandler;
use Shopware\Core\System\Snippet\SnippetValidator;

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

        $firstPath = 'storefront.de-DE.json';
        $secondPath = 'storefront.en-GB.json';
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
        $invalidData = $snippetValidator->getValidation();
        $missingSnippets = $invalidData->missingSnippets;

        static::assertCount(2, $missingSnippets);
        static::assertArrayHasKey('german', $missingSnippets['en-GB']);
        static::assertSame('german', $missingSnippets['en-GB']['german']['keyPath']);
        static::assertSame('exampleGerman', $missingSnippets['en-GB']['german']['availableValue']);

        static::assertArrayHasKey('english', $missingSnippets['de-DE']);
        static::assertSame('english', $missingSnippets['de-DE']['english']['keyPath']);
        static::assertSame('exampleEnglish', $missingSnippets['de-DE']['english']['availableValue']);

        $invalidPluralization = $invalidData->invalidPluralization;
        static::assertCount(0, $invalidPluralization);
    }

    public function testValidateShouldNotFindAnyMissingSnippets(): void
    {
        $snippetFileHandler = $this->getMockBuilder(SnippetFileHandler::class)
            ->disableOriginalConstructor()
            ->getMock();

        $firstPath = 'storefront.de-DE.json';
        $secondPath = 'storefront.en-GB.json';
        $snippetFileHandler->method('findAdministrationSnippetFiles')
            ->willReturn([$firstPath]);
        $snippetFileHandler->method('findStorefrontSnippetFiles')
            ->willReturn([$secondPath]);

        $snippetFileHandler->method('openJsonFile')
            ->willReturnCallback(fn () => ['foo' => 'bar']);

        $snippetValidator = new SnippetValidator(new SnippetFileCollection(), $snippetFileHandler, '');
        $invalidData = $snippetValidator->getValidation();

        static::assertCount(0, $invalidData->missingSnippets);
    }

    public function testValidateShouldFindInvalidPluralization(): void
    {
        $snippetFileHandler = $this->getMockBuilder(SnippetFileHandler::class)
            ->disableOriginalConstructor()
            ->getMock();

        $path = 'storefront.en-GB.json';
        $snippetFileHandler->method('findStorefrontSnippetFiles')
            ->willReturn([$path]);

        $expectedInvalidSnippets = [
            'noIndexes' => 'Singular | Plural',
            'noFallbackRange' => '{1}Singular | Plural',
            'noOneIndex' => '{0} Singular | [0,Inf[ Plural',
            'wrongPluralRangeSnippet' => '{1} Singular |]1,Inf[ Plural',
            'wrongPluralRangeSnippetDupe' => '{1} Singular DUPE |]1,Inf[ Plural DUPE',
        ];

        $actualSnippets = [
            'noPluralization' => 'Something',
            'somethingValid' => '{1} Singular |[0,Inf[ Plural',
            'somethingValidWith0' => '{0} Zero case | {1} Singular |[0,Inf[ Plural',
            ...$expectedInvalidSnippets,
        ];

        $snippetFileHandler->method('openJsonFile')
            ->willReturnCallback(fn () => $actualSnippets);

        $snippetValidator = new SnippetValidator(new SnippetFileCollection(), $snippetFileHandler, '');
        $invalidData = $snippetValidator->getValidation();
        $invalidPluralization = $invalidData->invalidPluralization;

        static::assertCount(5, $invalidPluralization);
        static::assertArrayNotHasKey('somethingValid', $invalidPluralization);
        static::assertArrayNotHasKey('somethingValidWith0', $invalidPluralization);

        foreach ($expectedInvalidSnippets as $expectedKey => $expectedValue) {
            static::assertArrayHasKey($expectedKey, $invalidPluralization, "Missing expected key: $expectedKey");

            $invalidSnippet = $invalidPluralization[$expectedKey];
            static::assertCount(4, $invalidSnippet);
            static::assertSame($expectedValue, $invalidSnippet['snippetValue'], "Invalid pluralization for key: $expectedKey");
            static::assertSame($path, $invalidSnippet['path'], "Invalid path for key: $expectedKey");
        }
    }
}
