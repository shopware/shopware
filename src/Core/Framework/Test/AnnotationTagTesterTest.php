<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
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

    public function testNoWrongDeprecationsIfThereAreNone(): void
    {
        $deprecatedContent = 'no deprecation here';

        static::expectException(NoDeprecationFoundException::class);
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
        static::expectExceptionMessage('Tag version must have 3 digits.');
        $this->annotationTagTester->validateDeprecatedAnnotations($deprecatedContent);
    }

    public function testTagVersionMustNotHaveLessThanThreeDigits(): void
    {
        $deprecatedContent = '@deprecated tag:v6.5';

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('Tag version must have 3 digits.');
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

    /**
     * @doesNotPerformAssertions - the test should check that no exception is thrown in this case
     */
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

    public function testExperimentalTagWithoutStableVersionPropertyThrowsException(): void
    {
        $deprecatedContent = '@experimental tag:v6.5.0';

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('Could not find indicator stableVersion in experimental annotation');
        $this->annotationTagTester->validateExperimentalAnnotations($deprecatedContent);
    }

    public function testExperimentalStableVersionMustNotHaveMoreThanThreeDigits(): void
    {
        $deprecatedContent = '@experimental stableVersion:v6.5.0.0';

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('Tag version must have 3 digits.');
        $this->annotationTagTester->validateExperimentalAnnotations($deprecatedContent);
    }

    public function testExperimentalStableVersionMustNotHaveLessThanThreeDigits(): void
    {
        $deprecatedContent = '@experimental stableVersion:v6.5';

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('Tag version must have 3 digits.');
        $this->annotationTagTester->validateExperimentalAnnotations($deprecatedContent);
    }

    public function testExperimentalStableVersionMustNotBeSmallerThanActualLiveVersion(): void
    {
        $deprecatedContent = '@experimental stableVersion:v6.3.0';

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('The version you used for deprecation or experimental annotation is already live.');
        $this->annotationTagTester->validateExperimentalAnnotations($deprecatedContent);
    }

    public function testExperimentalStableVersionMustNotBeTheSameAsTheLiveVersion(): void
    {
        $deprecatedContent = '@experimental stableVersion:v6.4.0';

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('The version you used for deprecation or experimental annotation is already live.');
        $this->annotationTagTester->validateExperimentalAnnotations($deprecatedContent);
    }

    /**
     * @doesNotPerformAssertions - the test should check that no exception is thrown in this case
     */
    public function testExperimentalStableVersionHigherThenLiveVersion(): void
    {
        $deprecatedContent = '@experimental stableVersion:v6.5.0';

        $this->annotationTagTester->validateExperimentalAnnotations($deprecatedContent);
    }
}
