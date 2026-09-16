<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook;

use Shopware\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class WebhookException extends HttpException
{
    public const WEBHOOK_FAILED = 'FRAMEWORK__WEBHOOK_FAILED';
    public const APP_WEBHOOK_FAILED = 'FRAMEWORK__APP_WEBHOOK_FAILED';
    public const UNSUPPORTED_MESSAGE = 'FRAMEWORK__WEBHOOK_UNSUPPORTED_MESSAGE';
    public const INVALID_DATA_MAPPING = 'FRAMEWORK__WEBHOOK_INVALID_DATA_MAPPING';
    public const UNKNOWN_DATA_TYPE = 'FRAMEWORK__WEBHOOK_UNKNOWN_DATA_TYPE';
    public const DUPLICATE_DESCRIBED_EVENT = 'FRAMEWORK__WEBHOOK_DUPLICATE_DESCRIBED_EVENT';
    public const TARGET_NOT_ALLOWED = 'FRAMEWORK__WEBHOOK_TARGET_NOT_ALLOWED';
    public const REDIRECT_TARGET_NOT_ALLOWED = 'FRAMEWORK__WEBHOOK_REDIRECT_TARGET_NOT_ALLOWED';
    public const MAXIMUM_REDIRECTS_EXCEEDED = 'FRAMEWORK__WEBHOOK_MAXIMUM_REDIRECTS_EXCEEDED';
    public const UNEXPECTED_CLASSIFICATION = 'FRAMEWORK__WEBHOOK_UNEXPECTED_CLASSIFICATION';
    public const HEALTH_API_DISABLED = 'FRAMEWORK__WEBHOOK_HEALTH_API_DISABLED';
    public const MISSING_INTEGRATION = 'FRAMEWORK__WEBHOOK_MISSING_INTEGRATION';
    public const APP_NOT_FOUND_FOR_INTEGRATION = 'FRAMEWORK__WEBHOOK_APP_NOT_FOUND_FOR_INTEGRATION';
    public const INVALID_REACTIVATION_NAMES = 'FRAMEWORK__WEBHOOK_INVALID_REACTIVATION_NAMES';
    public const TOO_MANY_REACTIVATION_NAMES = 'FRAMEWORK__WEBHOOK_TOO_MANY_REACTIVATION_NAMES';
    public const REACTIVATION_THROTTLED = 'FRAMEWORK__WEBHOOK_REACTIVATION_THROTTLED';
    public const OPERATOR_ROUTE_REQUIRES_USER = 'FRAMEWORK__WEBHOOK_OPERATOR_ROUTE_REQUIRES_USER';
    public const WEBHOOK_NOT_FOUND = 'FRAMEWORK__WEBHOOK_NOT_FOUND';

    public static function unexpectedClassification(string $classification): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::UNEXPECTED_CLASSIFICATION,
            'Webhook delivery outcome "{{ classification }}" cannot be recorded as a failure.',
            ['classification' => $classification]
        );
    }

    /**
     * The disabled health API answers 404 like a missing route; the error code still names
     * the feature so operators can tell a flag-off install from a typo.
     */
    public static function healthApiDisabled(): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::HEALTH_API_DISABLED,
            'The webhook health API is not available.'
        );
    }

    public static function missingIntegration(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_INTEGRATION,
            'The webhook health API requires authentication via an app integration.'
        );
    }

    public static function appNotFoundForIntegration(string $integrationId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::APP_NOT_FOUND_FOR_INTEGRATION,
            'No app is registered for the authenticated integration "{{ integrationId }}".',
            ['integrationId' => $integrationId]
        );
    }

    public static function invalidReactivationNames(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_REACTIVATION_NAMES,
            'The "names" parameter must be an array of webhook names.'
        );
    }

    public static function tooManyReactivationNames(int $given, int $max): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::TOO_MANY_REACTIVATION_NAMES,
            'A reactivation request accepts at most {{ max }} webhook names, got {{ given }}.',
            ['given' => $given, 'max' => $max]
        );
    }

    public static function reactivationThrottled(int $waitTime, \Throwable $e): self
    {
        return new self(
            Response::HTTP_TOO_MANY_REQUESTS,
            self::REACTIVATION_THROTTLED,
            'Too many webhook reactivation requests, try again in {{ seconds }} seconds.',
            ['seconds' => $waitTime],
            $e
        );
    }

    public static function operatorRouteRequiresUser(): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::OPERATOR_ROUTE_REQUIRES_USER,
            'This route requires an authenticated admin user.'
        );
    }

    public static function webhookNotFound(string $webhookId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::WEBHOOK_NOT_FOUND,
            'Webhook "{{ webhookId }}" not found.',
            ['webhookId' => $webhookId]
        );
    }

    public static function webhookFailedException(string $webhookId, \Throwable $e): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::WEBHOOK_FAILED,
            'Webhook "{{ webhookId }}" failed with error: {{ error }}.',
            ['webhookId' => $webhookId, 'error' => $e->getMessage()],
            $e
        );
    }

    public static function unsupportedMessage(string $actualClass): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::UNSUPPORTED_MESSAGE,
            'The webhook transport only supports WebhookEventMessage, got "{{ class }}".',
            ['class' => $actualClass]
        );
    }

    public static function appWebhookFailedException(string $webhookId, string $appId, \Throwable $e): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::APP_WEBHOOK_FAILED,
            'Webhook "{{ webhookId }}" from "{{ appId }}" failed with error: {{ error }}.',
            ['webhookId' => $webhookId, 'appId' => $appId, 'error' => $e->getMessage()],
            $e
        );
    }

    public static function targetNotAllowed(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::TARGET_NOT_ALLOWED,
            'Webhook target is not allowed.'
        );
    }

    public static function redirectTargetNotAllowed(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::REDIRECT_TARGET_NOT_ALLOWED,
            'Redirect target is not allowed.'
        );
    }

    public static function maximumRedirectsExceeded(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MAXIMUM_REDIRECTS_EXCEEDED,
            'Maximum redirects exceeded.'
        );
    }

    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'self')]
    public static function invalidDataMapping(string $propertyName, string $className): self|\RuntimeException
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_DATA_MAPPING,
            'Invalid available DataMapping, could not get property "{{ propertyName }}" on instance of {{ class }}',
            ['propertyName' => $propertyName, 'class' => $className]
        );
    }

    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'self')]
    public static function unknownEventDataType(string $type): self|\RuntimeException
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::UNKNOWN_DATA_TYPE,
            'Unknown EventDataType: {{ type }}',
            ['type' => $type]
        );
    }

    public static function duplicateDescribedEvent(string $eventName, string $describer): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DUPLICATE_DESCRIBED_EVENT,
            'Duplicate hookable event "{{ eventName }}" described by "{{ describer }}".',
            [
                'eventName' => $eventName,
                'describer' => $describer,
            ]
        );
    }
}
