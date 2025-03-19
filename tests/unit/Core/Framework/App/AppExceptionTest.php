<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\AppAlreadyInstalledException;
use Shopware\Core\Framework\App\Exception\AppDownloadException;
use Shopware\Core\Framework\App\Exception\AppNotFoundException;
use Shopware\Core\Framework\App\Validation\Error\AppNameError;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppException::class)]
class AppExceptionTest extends TestCase
{
    public function testCannotDeleteManaged(): void
    {
        $e = AppException::cannotDeleteManaged('ManagedApp');

        static::assertSame(AppException::CANNOT_DELETE_COMPOSER_MANAGED, $e->getErrorCode());
    }

    public function testNotCompatible(): void
    {
        $e = AppException::notCompatible('IncompatibleApp');

        static::assertSame(AppException::NOT_COMPATIBLE, $e->getErrorCode());
    }

    public function testNotFound(): void
    {
        $e = AppException::notFound('NonExistingApp');

        static::assertInstanceOf(AppNotFoundException::class, $e);
        static::assertSame(AppException::NOT_FOUND, $e->getErrorCode());
    }

    public function testAlreadyInstalled(): void
    {
        $e = AppException::alreadyInstalled('AlreadyInstalledApp');

        static::assertInstanceOf(AppAlreadyInstalledException::class, $e);
        static::assertSame(AppException::ALREADY_INSTALLED, $e->getErrorCode());
    }

    public function testRegistrationFailed(): void
    {
        $e = AppException::registrationFailed('ToBeRegisteredApp', 'Invalid signature');

        static::assertSame(AppException::REGISTRATION_FAILED, $e->getErrorCode());
        static::assertSame('App registration for "ToBeRegisteredApp" failed: Invalid signature', $e->getMessage());
    }

    public function testLicenseCouldNotBeVerified(): void
    {
        $e = AppException::licenseCouldNotBeVerified('UnlicensedApp');

        static::assertSame(AppException::LICENSE_COULD_NOT_BE_VERIFIED, $e->getErrorCode());
    }

    public function testInvalidConfiguration(): void
    {
        $e = AppException::invalidConfiguration('InvalidlyConfiguredApp', new AppNameError('InvalidlyConfiguredApp'));

        static::assertSame(AppException::INVALID_CONFIGURATION, $e->getErrorCode());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testInstallationFailed(): void
    {
        $e = AppException::installationFailed('AnyAppName', 'reason');

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertSame(AppException::INSTALLATION_FAILED, $e->getErrorCode());
        static::assertSame('App installation for "AnyAppName" failed: reason', $e->getMessage());
    }

    public function testAppSecretRequiredForFeatures(): void
    {
        $e = AppException::appSecretRequiredForFeatures('MyApp', ['Modules']);

        static::assertSame(AppException::FEATURES_REQUIRE_APP_SECRET, $e->getErrorCode());
        static::assertSame('App "MyApp" could not be installed/updated because it uses features Modules but has no secret', $e->getMessage());

        $e = AppException::appSecretRequiredForFeatures('MyApp', ['Modules', 'Payments', 'Webhooks']);

        static::assertSame(AppException::FEATURES_REQUIRE_APP_SECRET, $e->getErrorCode());
        static::assertSame('App "MyApp" could not be installed/updated because it uses features Modules, Payments and Webhooks but has no secret', $e->getMessage());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testInAppPurchaseGatewayUrlEmpty(): void
    {
        $e = AppException::inAppPurchaseGatewayUrlEmpty();

        static::assertSame(AppException::INVALID_CONFIGURATION, $e->getErrorCode());
        static::assertSame('No In-App Purchases gateway url set. Please update your manifest file.', $e->getMessage());
    }

    public function testNoSourceSupports(): void
    {
        $e = AppException::noSourceSupports();

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertSame('FRAMEWORK__APP_NO_SOURCE_SUPPORTS', $e->getErrorCode());
        static::assertSame('App is not supported by any source.', $e->getMessage());
    }

    public function testCannotMountAppFilesystem(): void
    {
        $previous = AppDownloadException::transportError('some/url');
        $e = AppException::cannotMountAppFilesystem('appName', $previous);

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertSame('FRAMEWORK__CANNOT_MOUNT_APP_FILESYSTEM', $e->getErrorCode());
        static::assertSame('Cannot mount a filesystem for App "appName". Error: "' . $previous->getMessage() . '"', $e->getMessage());
    }

    public function testSourceDoesNotExist(): void
    {
        $e = AppException::sourceDoesNotExist('/Unknown/Source');

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertSame('FRAMEWORK__APP_NO_SOURCE_SUPPORTS', $e->getErrorCode());
        static::assertSame('The source "/Unknown/Source" does not exist', $e->getMessage());
    }

    public function testCreateCommandValidationError(): void
    {
        $e = AppException::createCommandValidationError('error message');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame('FRAMEWORK__APP_CREATE_COMMAND_VALIDATION_ERROR', $e->getErrorCode());
        static::assertSame('error message', $e->getMessage());
    }

    public function testDirectoryAlreadyExists(): void
    {
        $e = AppException::directoryAlreadyExists('SuperApp');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame('FRAMEWORK__APP_DIRECTORY_ALREADY_EXISTS', $e->getErrorCode());
        static::assertSame('Directory for app "SuperApp" already exists', $e->getMessage());
    }

    public function testDirectoryCreationFailed(): void
    {
        $e = AppException::directoryCreationFailed('path/to/app');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame('FRAMEWORK__APP_DIRECTORY_CREATION_FAILED', $e->getErrorCode());
        static::assertSame('Unable to create directory "path/to/app". Please check permissions', $e->getMessage());
    }
}
