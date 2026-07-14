<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Feature;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class AppFeatureException extends AppException
{
    public const APP_FEATURE_UNKNOWN_FEATURE = 'FRAMEWORK__APP_FEATURE_UNKNOWN_FEATURE';
    public const APP_FEATURE_NOT_DECLARED = 'FRAMEWORK__APP_FEATURE_NOT_DECLARED';

    public static function unknownFeature(string $featureClass): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::APP_FEATURE_UNKNOWN_FEATURE,
            'No feature definition is registered for "{{ featureClass }}"',
            ['featureClass' => $featureClass]
        );
    }

    public static function notDeclared(string $appId, string $type, string $name): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::APP_FEATURE_NOT_DECLARED,
            'App "{{ appId }}" does not declare the "{{ type }}" feature "{{ name }}"',
            ['appId' => $appId, 'type' => $type, 'name' => $name]
        );
    }
}
