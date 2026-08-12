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
#[Package('framework')]
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

        static::assertSame('6.4.0.0', $version);
    }

    public function testGetVersionFromGitTagReturnsCorrectVersionFromOldVersioning(): void
    {
        $version = AnnotationTagTester::getPlatformVersionFromGitTag('v6.1.0');

        static::assertSame('6.1.0', $version);
    }

    public function testGetVersionFromGitTagAllowsHighVersions(): void
    {
        $version = AnnotationTagTester::getPlatformVersionFromGitTag('v200.123.1.36');

        static::assertSame('200.123.1.36', $version);
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

        static::assertSame('1.0', $version);
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

    #[DoesNotPerformAssertions]
    public function testBCChangeAttributeWithFutureVersionDoesNotThrowException(): void
    {
        $this->annotationTagTester->validateBCChangeAttributeVersions(
            '#[ReturnTypeNarrowing(version: \'v6.5.0\', newType: \'static\')]'
        );
    }

    #[DoesNotPerformAssertions]
    public function testBCChangeAttributeWithPositionalVersionDoesNotThrowException(): void
    {
        $this->annotationTagTester->validateBCChangeAttributeVersions(
            '#[BecomesFinal(\'v6.5.0\')]'
        );
    }

    public function testBCChangeAttributeWithLiveVersionThrowsException(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('The version you used for deprecation or experimental annotation is already live.'));

        $this->annotationTagTester->validateBCChangeAttributeVersions(
            '#[NewOptionalParameter(version: \'v6.4.0\', parameterName: \'states\', parameterType: \'array\')]'
        );
    }

    public function testParameterDefaultValueChangeWithLiveVersionThrowsException(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('The version you used for deprecation or experimental annotation is already live.'));

        $this->annotationTagTester->validateBCChangeAttributeVersions(
            '#[ParameterDefaultValueChange(version: \'v6.4.0\', parameterName: \'value\', newDefaultValue: \'new\')]'
        );
    }

    public function testBCChangeAttributeWithMalformedVersionThrowsException(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('The tag version should start with `v` and comprise 3 digits separated by periods.'));

        $this->annotationTagTester->validateBCChangeAttributeVersions(
            '#[BecomesInternal(version: \'6.5.0\')]'
        );
    }

    public function testDeprecatedWithoutPropertiesWillThrowException(): void
    {
        $deprecatedContent = '@deprecated';

        $this->expectExceptionObject(new \InvalidArgumentException('Could not find indicator manifest or tag in deprecation annotation.'));
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

        $this->expectExceptionObject(new \InvalidArgumentException('Could not find indicator manifest or tag in deprecation annotation.'));
        $this->annotationTagTester->validateDeprecatedAnnotations($deprecatedContent);
    }

    public function testDeprecationTagWithUnknownPrefixThrowsException(): void
    {
        $deprecatedContent = '@deprecated administration:v6.5.0.0';

        $this->expectExceptionObject(new \InvalidArgumentException('Could not find indicator manifest or tag in deprecation annotation.'));
        $this->annotationTagTester->validateDeprecatedAnnotations($deprecatedContent);
    }

    public function testTagVersionMustNotHaveMoreThanThreeDigits(): void
    {
        $deprecatedContent = '@deprecated tag:v6.5.0.0';

        $this->expectExceptionObject(new \InvalidArgumentException('The tag version should start with `v` and comprise 3 digits separated by periods.'));
        $this->annotationTagTester->validateDeprecatedAnnotations($deprecatedContent);
    }

    public function testTagVersionMustNotHaveLessThanThreeDigits(): void
    {
        $deprecatedContent = '@deprecated tag:v6.5';

        $this->expectExceptionObject(new \InvalidArgumentException('The tag version should start with `v` and comprise 3 digits separated by periods.'));
        $this->annotationTagTester->validateDeprecatedAnnotations($deprecatedContent);
    }

    public function testTagVersionMustNotBeSmallerThanActualLiveVersion(): void
    {
        $deprecatedContent = '@deprecated tag:v6.3.0';

        $this->expectExceptionObject(new \InvalidArgumentException('The version you used for deprecation or experimental annotation is already live.'));
        $this->annotationTagTester->validateDeprecatedAnnotations($deprecatedContent);
    }

    public function testTagVersionMustNotBeTheSameAsTheLiveVersion(): void
    {
        $deprecatedContent = '@deprecated tag:v6.4.0';

        $this->expectExceptionObject(new \InvalidArgumentException('The version you used for deprecation or experimental annotation is already live.'));
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

        $this->expectExceptionObject(new \InvalidArgumentException('Manifest version must have 2 digits.'));
        $this->annotationTagTester->validateDeprecatedAnnotations($deprecatedContent);
    }

    public function testManifestVersionMustNotBeSmallerThanActualLiveVersion(): void
    {
        $deprecatedContent = '@deprecated manifest:v1.0';

        $this->expectExceptionObject(new \InvalidArgumentException('The version you used for deprecation or experimental annotation is already live.'));
        $this->annotationTagTester->validateDeprecatedAnnotations($deprecatedContent);
    }

    public function testManifestVersionMustNotBeTheSameAsTheLiveVersion(): void
    {
        $deprecatedContent = '@deprecated manifest:v0.1';

        $this->expectExceptionObject(new \InvalidArgumentException('The version you used for deprecation or experimental annotation is already live.'));
        $this->annotationTagTester->validateDeprecatedAnnotations($deprecatedContent);
    }

    public function testItCapturesTheVersionFromDeprecationElementsCorrectly(): void
    {
        $this->annotationTagTester->validateDeprecationElements('<deprecated>tag:v6.5.0</deprecated>');

        $this->expectExceptionObject(new \InvalidArgumentException('The version you used for deprecation or experimental annotation is already live.'));
        $this->annotationTagTester->validateDeprecationElements('<deprecated>tag:v6.3.0</deprecated>');
    }

    public function testIncorrectDeprecationTagFormat(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Deprecation tag is not found in the file.'));
        $this->annotationTagTester->validateDeprecationElements('<deprecatedd>tag:v6.5</deprecatedd>');
    }

    #[DataProvider('incorrectExperimentalAnnotationsFormatProvider')]
    public function testExperimentalWithIncorrectPropertiesDeclarationWillThrowException(string $content): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Incorrect format for experimental annotation. Properties `stableVersion` and/or `feature` are not declared.'));
        $this->annotationTagTester->validateExperimentalAnnotations($content);
    }

    public static function incorrectExperimentalAnnotationsFormatProvider(): \Generator
    {
        yield 'No properties added' => ['@experimental'];
        yield 'Incorrect separator' => ['@experimental stableVersion=v6.5.0 feature1=testFeature'];
    }

    #[DataProvider('experimentalAnnotationsWithoutStableVersionProvider')]
    public function testExperimentalWithoutStableVersionPropertyThrowsException(string $content): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Could not find property stableVersion in experimental annotation.'));
        $this->annotationTagTester->validateExperimentalAnnotations($content);
    }

    public static function experimentalAnnotationsWithoutStableVersionProvider(): \Generator
    {
        yield 'Only feature property' => ['@experimental feature:TEST_FEATURE'];
    }

    #[DataProvider('experimentalAnnotationsWithUnknownPropertyProvider')]
    public function testExperimentalWithUnknownPropertyThrowsException(string $content): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unknown propert/');
        $this->annotationTagTester->validateExperimentalAnnotations($content);
    }

    public static function experimentalAnnotationsWithUnknownPropertyProvider(): \Generator
    {
        yield 'Typo in feature key' => ['@experimental stableVersion:v6.5.0 feture:lower'];
        yield 'Unknown property key' => ['@experimental stableVersion:v6.5.0 name:testFeature'];
        yield 'Unknown key for the version' => ['@experimental tag:v6.5.0 feature:TEST_FEATURE'];
    }

    #[DoesNotPerformAssertions]
    public function testExperimentalWithOnlyStableVersionPropertyDoesNotThrow(): void
    {
        // `feature` is optional: an ungated experimental API declares only `stableVersion`.
        $this->annotationTagTester->validateExperimentalAnnotations('@experimental stableVersion:v6.5.0');
    }

    #[DoesNotPerformAssertions]
    #[DataProvider('experimentalAnnotationsWithTrailingTextProvider')]
    public function testExperimentalWithTrailingTextDoesNotThrow(string $content): void
    {
        // Non-property tokens after the known properties (a closing docblock terminator or an
        // explanatory note) are ignored, as long as no unknown `key:value` property is present.
        $this->annotationTagTester->validateExperimentalAnnotations($content);
    }

    public static function experimentalAnnotationsWithTrailingTextProvider(): \Generator
    {
        yield 'Single-line annotation terminator' => ['/** @experimental stableVersion:v6.5.0 feature:TEST_FEATURE */'];
        yield 'Explanatory note after feature' => ['@experimental stableVersion:v6.5.0 feature:TEST_FEATURE this is a temporary note'];
    }

    public function testExperimentalStableVersionMustNotHaveMoreThanThreeDigits(): void
    {
        $deprecatedContent = '@experimental stableVersion:v6.5.0.0 feature:TEST_FEATURE';

        $this->expectExceptionObject(new \InvalidArgumentException('The tag version should start with `v` and comprise 3 digits separated by periods.'));
        $this->annotationTagTester->validateExperimentalAnnotations($deprecatedContent);
    }

    public function testExperimentalStableVersionMustStartFromV(): void
    {
        $deprecatedContent = '@experimental stableVersion:a6.5.0 feature:TEST_FEATURE';

        $this->expectExceptionObject(new \InvalidArgumentException('The tag version should start with `v` and comprise 3 digits separated by periods.'));
        $this->annotationTagTester->validateExperimentalAnnotations($deprecatedContent);
    }

    public function testExperimentalStableVersionMustNotHaveLessThanThreeDigits(): void
    {
        $deprecatedContent = '@experimental stableVersion:v6.5 feature:TEST_FEATURE';

        $this->expectExceptionObject(new \InvalidArgumentException('The tag version should start with `v` and comprise 3 digits separated by periods.'));
        $this->annotationTagTester->validateExperimentalAnnotations($deprecatedContent);
    }

    public function testExperimentalStableVersionMustNotBeSmallerThanActualLiveVersion(): void
    {
        $deprecatedContent = '@experimental stableVersion:v6.3.0 feature:TEST_FEATURE';

        $this->expectExceptionObject(new \InvalidArgumentException('The version you used for deprecation or experimental annotation is already live.'));
        $this->annotationTagTester->validateExperimentalAnnotations($deprecatedContent);
    }

    public function testExperimentalStableVersionMustNotBeTheSameAsTheLiveVersion(): void
    {
        $deprecatedContent = '@experimental stableVersion:v6.4.0 feature:TEST_FEATURE';

        $this->expectExceptionObject(new \InvalidArgumentException('The version you used for deprecation or experimental annotation is already live.'));
        $this->annotationTagTester->validateExperimentalAnnotations($deprecatedContent);
    }

    #[DataProvider('incorrectFeaturePropertyValueProvider')]
    public function testExperimentalWithIncorrectFeatureValueWillThrowException(string $content): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('The value of feature-property can not be empty, contain white spaces and must be in ALL_CAPS format.'));
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
