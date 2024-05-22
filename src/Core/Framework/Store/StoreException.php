<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Exception\ExtensionNotFoundException;
use Shopware\Core\Framework\Store\Exception\ExtensionUpdateRequiresConsentAffirmationException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class StoreException extends HttpException
{
    public const CANNOT_DELETE_COMPOSER_MANAGED = 'FRAMEWORK__STORE_CANNOT_DELETE_COMPOSER_MANAGED';
    public const EXTENSION_THEME_STILL_IN_USE = 'FRAMEWORK__EXTENSION_THEME_STILL_IN_USE';
    public const EXTENSION_INSTALL = 'FRAMEWORK__EXTENSION_INSTALL_EXCEPTION';
    public const EXTENSION_UPDATE_REQUIRES_CONSENT_AFFIRMATION = 'FRAMEWORK__EXTENSION_UPDATE_REQUIRES_CONSENT_AFFIRMATION';
    public const EXTENSION_NOT_FOUND = 'FRAMEWORK__EXTENSION_NOT_FOUND';
    public const CANNOT_UPLOAD_CORRECTLY = 'FRAMEWORK__EXTENSION_CANNOT_BE_UPLOADED_CORRECTLY';
    public const EXTENSION_RUNTIME_EXTENSION_MANAGEMENT_NOT_ALLOWED = 'FRAMEWORK__EXTENSION_RUNTIME_EXTENSION_MANAGEMENT_NOT_ALLOWED';

    public static function cannotDeleteManaged(string $pluginName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CANNOT_DELETE_COMPOSER_MANAGED,
            'Extension {{ name }} is managed by Composer and cannot be deleted',
            ['name' => $pluginName]
        );
    }

    public static function extensionThemeStillInUse(string $extensionId): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::EXTENSION_THEME_STILL_IN_USE,
            'The extension with id "{{ extensionId }}" can not be removed because its theme is still assigned to a sales channel.',
            ['extensionId' => $extensionId]
        );
    }

    public static function extensionInstallException(string $message): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::EXTENSION_INSTALL,
            $message
        );
    }

    /**
     * @param array<string, array<string, mixed>> $deltas
     */
    public static function extensionUpdateRequiresConsentAffirmationException(string $appName, array $deltas): self
    {
        return new ExtensionUpdateRequiresConsentAffirmationException(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::EXTENSION_UPDATE_REQUIRES_CONSENT_AFFIRMATION,
            'Updating app "{{ appName }}" requires a renewed consent affirmation.',
            ['appName' => $appName, 'deltas' => $deltas]
        );
    }

    public static function extensionNotFoundFromId(string $id): self
    {
        return new ExtensionNotFoundException(
            Response::HTTP_NOT_FOUND,
            self::EXTENSION_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'extension', 'field' => 'id', 'value' => $id]
        );
    }

    public static function extensionNotFoundFromTechnicalName(string $technicalName): self
    {
        return new ExtensionNotFoundException(
            Response::HTTP_NOT_FOUND,
            self::EXTENSION_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'extension', 'field' => 'technical name', 'value' => $technicalName]
        );
    }

    public static function couldNotUploadExtensionCorrectly(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CANNOT_UPLOAD_CORRECTLY,
            'Extension could not be uploaded correctly.'
        );
    }

    public static function extensionRuntimeExtensionManagementNotAllowed(): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::EXTENSION_RUNTIME_EXTENSION_MANAGEMENT_NOT_ALLOWED,
            'Runtime extension management is disabled'
        );
    }
}
