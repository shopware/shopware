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

        $firstPath = 'storefront.de.json';
        $secondPath = 'storefront.en.json';
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
        $missingSnippets = $invalidData->missingSnippets->getElements();
        static::assertCount(2, $missingSnippets);

        $missingSnippetEnGB = $missingSnippets[0];
        static::assertSame('german', $missingSnippetEnGB->getKeyPath());
        static::assertSame('exampleGerman', $missingSnippetEnGB->getAvailableTranslation());

        $missingSnippetdeDE = $missingSnippets[1];
        static::assertSame('english', $missingSnippetdeDE->getKeyPath());
        static::assertSame('exampleEnglish', $missingSnippetdeDE->getAvailableTranslation());

        $invalidPluralization = $invalidData->invalidPluralization;
        static::assertCount(0, $invalidPluralization);
    }

    public function testValidateShouldNotFindAnyMissingSnippets(): void
    {
        $snippetFileHandler = $this->getMockBuilder(SnippetFileHandler::class)
            ->disableOriginalConstructor()
            ->getMock();

        $firstPath = 'storefront.de.json';
        $secondPath = 'storefront.en.json';
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

        $path = 'storefront.en.json';
        $snippetFileHandler->method('findStorefrontSnippetFiles')
            ->willReturn([$path]);

        $expectedInvalidSnippets = [
            'noIndexes' => 'Singular | Plural',
            'noFallbackRange' => '{1}Singular | Plural',
            'noOneIndex' => '{0} Singular | [0,Inf[ Plural',
            'wrongPluralRangeSnippetFixable' => '{1} Singular |]1,Inf[ Plural',
            'wrongPluralRangeSnippetDupeFixable' => '{1} Singular DUPE |]1,Inf[ Plural DUPE',
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
        static::assertFalse($invalidPluralization->has('somethingValid'));
        static::assertFalse($invalidPluralization->has('somethingValidWith0'));

        foreach ($expectedInvalidSnippets as $expectedKey => $expectedValue) {
            static::assertTrue($invalidPluralization->has($expectedKey), "Missing expected key: $expectedKey");

            $invalidSnippet = $invalidPluralization->get($expectedKey);
            static::assertSame($expectedValue, $invalidSnippet->snippetValue, "Invalid pluralization for key: $expectedKey");
            static::assertSame($path, $invalidSnippet->path, "Invalid path for key: $expectedKey");
            static::assertSame(\str_contains($expectedKey, 'Fixable'), $invalidSnippet->isFixable);
        }
    }
}
