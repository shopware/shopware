<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\App\Validation\AppNameValidator;
use Shopware\Core\Framework\App\Validation\Error\AppNameError;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppNameValidator::class)]
class AppNameValidatorTest extends TestCase
{
    private AppNameValidator $appNameValidator;

    protected function setUp(): void
    {
        $this->appNameValidator = new AppNameValidator($this->createResolverFor(
            static fn (Manifest $manifest) => $manifest->getPath()
        ));
    }

    public function testValidate(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');

        $violations = $this->appNameValidator->validate($manifest, null);
        static::assertCount(0, $violations);
    }

    public function testValidateNonCaseSensitive(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');
        $manifest->getMetadata()->assign(['name' => 'TeSt']);

        $violations = $this->appNameValidator->validate($manifest, null);
        static::assertCount(0, $violations);
    }

    public function testValidateReturnsErrors(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/invalidAppName/manifest.xml');

        $violations = $this->appNameValidator->validate($manifest, null);

        static::assertCount(1, $violations);
        static::assertInstanceOf(AppNameError::class, $violations[0]);
        static::assertStringContainsString('The technical app name "notSameAppNameAsFolder" in the "manifest.xml" and the folder name must be equal.', $violations[0]->getMessage());
    }

    public function testTheNameComesFromTheResolvedFilesystemNotTheManifestPath(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');

        // A service manifest carries its host URL in the path, and its files resolve to a directory
        // named after the manifest, so the name still matches.
        $manifest->setPath('https://my-service.example.com');

        $validator = new AppNameValidator($this->createResolverFor(
            static fn (Manifest $manifest) => '/tmp/services/' . $manifest->getMetadata()->getName()
        ));

        static::assertCount(0, $validator->validate($manifest, null));
    }

    private function createResolverFor(\Closure $location): SourceResolver
    {
        $resolver = static::createStub(SourceResolver::class);
        $resolver->method('filesystemForManifest')
            ->willReturnCallback(static fn (Manifest $manifest) => new Filesystem($location($manifest)));

        return $resolver;
    }
}
