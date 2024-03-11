<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Test\AnnotationTagTester;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(AnnotationTagTester::class)]
class AnnotationTagTesterTest extends TestCase
{
    private AnnotationTagTester $annotationTagTester;

    protected function setUp(): void
    {
        $this->annotationTagTester = new AnnotationTagTester('6.4.0.0', '1.0');
    }

    public function testGetVersionFromGitTagReturnsCorrectVersion(): void
    {
        $version = AnnotationTagTester::getPlatformVersionFromGitTag('v6.4.0.0');

        static::assertEquals('6.4.0.0', $version);
    }

    public function testGetVersionFromGitTagReturnsCorrectVersionFromOldVersioning(): void
    {
        $version = AnnotationTagTester::getPlatformVersionFromGitTag('v6.1.0');

        static::assertEquals('6.1.0', $version);
    }

    public function testGetVersionFromGitTagAllowsHighVersions(): void
    {
        $version = AnnotationTagTester::getPlatformVersionFromGitTag('v200.123.1.36');

        static::assertEquals('200.123.1.36', $version);
    }

    public function testGetVersionFromGitTagsValidatesThatVersionIsStartingWithV(): void
    {
        $version = AnnotationTagTester::getPlatformVersionFromGitTag('6.4.0.0');

        static::assertNull($version);
    }

    public function testGetVersionFromGitTagsDoesNotCaptureVersionsWithSuffix(): void
    {
        $version = AnnotationTagTester::getPlatformVersionFromGitTag('v6.4.0.0-RC');

        static::assertNull($version);
    }

    public function testGetVersionFromGitTagDoesNotCaptureWildCards(): void
    {
        $version = AnnotationTagTester::getPlatformVersionFromGitTag('v6.4.*');

        static::assertNull($version);
    }

    public function testGetVersionFromGitTagsDoesNotCaptureMalformedVersions(): void
    {
        $version = AnnotationTagTester::getPlatformVersionFromGitTag('v6.4..2.5');

        static::assertNull($version);
    }

    public function testGetVersionFromGitTagsDoesNotCaptureSingleDigits(): void
    {
        $version = AnnotationTagTester::getPlatformVersionFromGitTag('v6');

        static::assertNull($version);
    }

    public function testGetVersionFromGitTagsDoesNotCaptureVersionsWithFiveDigits(): void
    {
        $version = AnnotationTagTester::getPlatformVersionFromGitTag('v6.4.0.0.0');

        static::assertNull($version);
    }

    public function testGetVersionFromManifestFileNameReturnsVersionFromManifestNamingSchema(): void
    {
        $version = AnnotationTagTester::getVersionFromManifestFileName('manifest-1.0.xsd');

        static::assertEquals('1.0', $version);
    }

    public function testGetVersionFromManifestFileNameReturnsNullIfFileNameIsInWrongSchema(): void
    {
        $version = AnnotationTagTester::getVersionFromManifestFileName('manifesto-1.0.xsd');

        static::assertNull($version);
    }

    public function testGetVersionFromManifestFileNameReturnsNullIfVersionHasMoreThanTwoDigits(): void
    {
        $version = AnnotationTagTester::getVersionFromManifestFileName('manifest-1.0.0.xsd');

        static::assertNull($version);
    }

    public function testGetVersionFromManifestFileNameReturnsNullIfManifestFileHasWrongExtension(): void
    {
        $version = AnnotationTagTester::getVersionFromManifestFileName('manifest-1.0.xml');

        static::assertNull($version);
    }

    public function testDeprecatedWithoutPropertiesWillThrowException(): void
    {
        $deprecatedContent = '@deprecated';

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('Could not find indicator manifest or tag in deprecation annotation');
        $this->annotationTagTester->validateDeprecatedAnnotations($deprecatedContent);
    }

    public function testCorrectDeprecationTagDoesNotThrowException(): void
    {
        $deprecatedContent = '@deprecated tag:v6.5.0';

        static::expectNotToPerformAssertions();
        $this->annotationTagTester->validateDeprecatedAnnotations($deprecatedContent);
    }

    public function testDeprecationTagWithoutVersionThrowsException(): void
    {
        $deprecatedContent = '@deprecated will be removed';

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('Could not find indicator manifest or tag in deprecation');
        $this->annotationTagTester->validateDeprecatedAnnotations($deprecatedContent);
    }

    public function testDeprecationTagWithUnknownPrefixThrowsException(): void
    {
        $deprecatedContent = '@deprecated administration:v6.5.0.0';

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('Could not find indicator manifest or tag in deprecation');
        $this->annotationTagTester->validateDeprecatedAnnotations($deprecatedContent);
    }

    public function testTagVersionMustNotHaveMoreThanThreeDigits(): void
    {
        $deprecatedContent = '@deprecated tag:v6.5.0.0';

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('The tag version should start with `v` and comprise 3 digits separated by periods.');
        $this->annotationTagTester->validateDeprecatedAnnotations($deprecatedContent);
    }

    public function testTagVersionMustNotHaveLessThanThreeDigits(): void
    {
        $deprecatedContent = '@deprecated tag:v6.5';

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('The tag version should start with `v` and comprise 3 digits separated by periods.');
        $this->annotationTagTester->validateDeprecatedAnnotations($deprecatedContent);
    }

    public function testTagVersionMustNotBeSmallerThanActualLiveVersion(): void
    {
        $deprecatedContent = '@deprecated tag:v6.3.0';

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('The version you used for deprecation or experimental annotation is already live.');
        $this->annotationTagTester->validateDeprecatedAnnotations($deprecatedContent);
    }

    public function testTagVersionMustNotBeTheSameAsTheLiveVersion(): void
    {
        $deprecatedContent = '@deprecated tag:v6.4.0';

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('The version you used for deprecation or experimental annotation is already live.');
        $this->annotationTagTester->validateDeprecatedAnnotations($deprecatedContent);
    }

    #[DoesNotPerformAssertions]
    public function testTagVersionHigherThenLiveVersion(): void
    {
        $deprecatedContent = '@deprecated tag:v6.5.0';

        $this->annotationTagTester->validateDeprecatedAnnotations($deprecatedContent);
    }

    public function testManifestVersionMustNotHaveLessThanTwoDigits(): void
    {
        $deprecatedContent = '@deprecated manifest:v1';

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('Manifest version must have 2 digits.');
        $this->annotationTagTester->validateDeprecatedAnnotations($deprecatedContent);
    }

    public function testManifestVersionMustNotBeSmallerThanActualLiveVersion(): void
    {
        $deprecatedContent = '@deprecated manifest:v1.0';

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('The version you used for deprecation or experimental annotation is already live.');
        $this->annotationTagTester->validateDeprecatedAnnotations($deprecatedContent);
    }

    public function testManifestVersionMustNotBeTheSameAsTheLiveVersion(): void
    {
        $deprecatedContent = '@deprecated manifest:v0.1';

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('The version you used for deprecation or experimental annotation is already live.');
        $this->annotationTagTester->validateDeprecatedAnnotations($deprecatedContent);
    }

    public function testItCapturesTheVersionFromDeprecationElementsCorrectly(): void
    {
        $this->annotationTagTester->validateDeprecationElements('<deprecated>tag:v6.5.0</deprecated>');

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('The version you used for deprecation or experimental annotation is already live.');
        $this->annotationTagTester->validateDeprecationElements('<deprecated>tag:v6.3.0</deprecated>');
    }

    public function testIncorrectDeprecationTagFormat(): void
    {
        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('Deprecation tag is not found in the file.');
        $this->annotationTagTester->validateDeprecationElements('<deprecatedd>tag:v6.5</deprecatedd>');
    }

    #[DataProvider('incorrectExperimentalAnnotationsFormatProvider')]
    public function testExperimentalWithIncorrectPropertiesDeclarationWillThrowException(string $content): void
    {
        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('Incorrect format for experimental annotation. Properties `stableVersion` and/or `feature` are not declared');
        $this->annotationTagTester->validateExperimentalAnnotations($content);
    }

    public static function incorrectExperimentalAnnotationsFormatProvider(): \Generator
    {
        yield 'No properties added' => ['@experimental'];
        yield 'Added only stableVersion property' => ['@experimental stableVersion:v6.5.0'];
        yield 'Added only feature property' => ['@experimental feature:TEST_FEATURE'];
        yield 'Incorrect separator' => ['@experimental stableVersion=v6.5.0 feature1=testFeature'];
    }

    public function testExperimentalTagWithoutStableVersionPropertyThrowsException(): void
    {
        $deprecatedContent = '@experimental tag:v6.5.0 feature:TEST_FEATURE';

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('Could not find property stableVersion in experimental annotation');
        $this->annotationTagTester->validateExperimentalAnnotations($deprecatedContent);
    }

    public function testExperimentalWithoutFeaturePropertyWillThrowException(): void
    {
        $deprecatedContent = '@experimental stableVersion:v6.5.0 name:testFeature';

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('Could not find property feature in experimental annotation');
        $this->annotationTagTester->validateExperimentalAnnotations($deprecatedContent);
    }

    public function testExperimentalStableVersionMustNotHaveMoreThanThreeDigits(): void
    {
        $deprecatedContent = '@experimental stableVersion:v6.5.0.0 feature:TEST_FEATURE';

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('The tag version should start with `v` and comprise 3 digits separated by periods.');
        $this->annotationTagTester->validateExperimentalAnnotations($deprecatedContent);
    }

    public function testExperimentalStableVersionMustStartFromV(): void
    {
        $deprecatedContent = '@experimental stableVersion:a6.5.0 feature:TEST_FEATURE';

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('The tag version should start with `v` and comprise 3 digits separated by periods.');
        $this->annotationTagTester->validateExperimentalAnnotations($deprecatedContent);
    }

    public function testExperimentalStableVersionMustNotHaveLessThanThreeDigits(): void
    {
        $deprecatedContent = '@experimental stableVersion:v6.5 feature:TEST_FEATURE';

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('The tag version should start with `v` and comprise 3 digits separated by periods.');
        $this->annotationTagTester->validateExperimentalAnnotations($deprecatedContent);
    }

    public function testExperimentalStableVersionMustNotBeSmallerThanActualLiveVersion(): void
    {
        $deprecatedContent = '@experimental stableVersion:v6.3.0 feature:TEST_FEATURE';

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('The version you used for deprecation or experimental annotation is already live.');
        $this->annotationTagTester->validateExperimentalAnnotations($deprecatedContent);
    }

    public function testExperimentalStableVersionMustNotBeTheSameAsTheLiveVersion(): void
    {
        $deprecatedContent = '@experimental stableVersion:v6.4.0 feature:TEST_FEATURE';

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('The version you used for deprecation or experimental annotation is already live.');
        $this->annotationTagTester->validateExperimentalAnnotations($deprecatedContent);
    }

    #[DataProvider('incorrectFeaturePropertyValueProvider')]
    public function testExperimentalWithIncorrectFeatureValueWillThrowException(string $content): void
    {
        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('The value of feature-property can not be empty, contain white spaces and must be in ALL_CAPS format.');
        $this->annotationTagTester->validateExperimentalAnnotations($content);
    }

    public static function incorrectFeaturePropertyValueProvider(): \Generator
    {
        yield 'Incorrect symbols' => ['@experimental stableVersion:v6.5.0 feature:here+Incorrect-Symbols'];
        yield 'Used camelCase instead of ALL_CAPS' => ['@experimental stableVersion:v6.5.0 feature:here+Incorrect-Symbols'];
        yield 'Used snake_case instead of ALL_CAPS' => ['@experimental stableVersion:v6.5.0 feature:feature_name'];
        yield 'Empty feature value' => ['@experimental stableVersion:v6.5.0 feature:'];
    }

    #[DoesNotPerformAssertions]
    public function testExperimentalStableVersionHigherThanLiveVersion(): void
    {
        $deprecatedContent = '@experimental stableVersion:v6.5.0 feature:TEST_FEATURE';

        $this->annotationTagTester->validateExperimentalAnnotations($deprecatedContent);
    }
}
