<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Exception\ExtensionInstallException;
use Shopware\Core\Framework\Store\Exception\ExtensionNotFoundException;
use Shopware\Core\Framework\Store\Exception\ExtensionThemeStillInUseException;
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
        return new ExtensionThemeStillInUseException($extensionId);
    }

    public static function extensionInstallException(string $message): self
    {
        return new ExtensionInstallException($message);
    }

    /**
     * @param array<string, array<string, mixed>> $deltas
     */
    public static function extensionUpdateRequiresConsentAffirmationException(string $appName, array $deltas): self
    {
        return ExtensionUpdateRequiresConsentAffirmationException::fromDelta($appName, $deltas);
    }

    public static function extensionNotFoundFromId(string $id): self
    {
        return ExtensionNotFoundException::fromId($id);
    }

    public static function extensionNotFoundFromTechnicalName(string $technicalName): self
    {
        return ExtensionNotFoundException::fromTechnicalName($technicalName);
    }
}
