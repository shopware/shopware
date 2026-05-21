<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 */
#[Package('framework')]
class UcpException extends HttpException
{
    public const FEATURE_DISABLED = 'UCP__FEATURE_DISABLED';
    public const SALES_CHANNEL_NOT_CONFIGURED = 'UCP__SALES_CHANNEL_NOT_CONFIGURED';
    public const CAPABILITY_NOT_ENABLED = 'UCP__CAPABILITY_NOT_ENABLED';
    public const INVALID_PROFILE_URL = 'UCP__INVALID_PROFILE_URL';
    public const PROFILE_UNREACHABLE = 'UCP__PROFILE_UNREACHABLE';
    public const PROFILE_MALFORMED = 'UCP__PROFILE_MALFORMED';
    public const PROFILE_NAMESPACE_MISMATCH = 'UCP__PROFILE_NAMESPACE_MISMATCH';
    public const VERSION_UNSUPPORTED = 'UCP__VERSION_UNSUPPORTED';
    public const VERSION_INVALID = 'UCP__VERSION_INVALID';
    public const CAPABILITIES_INCOMPATIBLE = 'UCP__CAPABILITIES_INCOMPATIBLE';
    public const SIGNATURE_MISSING = 'UCP__SIGNATURE_MISSING';
    public const SIGNATURE_INVALID = 'UCP__SIGNATURE_INVALID';
    public const SIGNATURE_KEY_NOT_FOUND = 'UCP__SIGNATURE_KEY_NOT_FOUND';
    public const SIGNATURE_ALGORITHM_UNSUPPORTED = 'UCP__SIGNATURE_ALGORITHM_UNSUPPORTED';
    public const DIGEST_MISMATCH = 'UCP__DIGEST_MISMATCH';
    public const KEY_NOT_FOUND = 'UCP__KEY_NOT_FOUND';
    public const KEY_GENERATION_FAILED = 'UCP__KEY_GENERATION_FAILED';
    public const KEY_DECRYPTION_FAILED = 'UCP__KEY_DECRYPTION_FAILED';
    public const KEY_CANNOT_BE_DELETED = 'UCP__KEY_CANNOT_BE_DELETED';
    public const ENCRYPTION_FAILED = 'UCP__ENCRYPTION_FAILED';
    public const DISCOVERY_BUDGET_EXCEEDED = 'UCP__DISCOVERY_BUDGET_EXCEEDED';
    public const IDEMPOTENCY_KEY_CONFLICT = 'UCP__IDEMPOTENCY_KEY_CONFLICT';
    public const IDEMPOTENCY_KEY_REQUIRED = 'UCP__IDEMPOTENCY_KEY_REQUIRED';
    public const SCOPE_REQUIRED = 'UCP__SCOPE_REQUIRED';
    public const SIGNALS_UNTRUSTED = 'UCP__SIGNALS_UNTRUSTED';
    public const INVALID_CURSOR = 'UCP__INVALID_CURSOR';
    public const INVALID_ARGUMENT = 'UCP__INVALID_ARGUMENT';
    public const CAPABILITY_INVALID_TAG = 'UCP__CAPABILITY_INVALID_TAG';
    public const MCP_TOOL_INVALID_TAG = 'UCP__MCP_TOOL_INVALID_TAG';
    public const PAYMENT_HANDLER_INVALID_TAG = 'UCP__PAYMENT_HANDLER_INVALID_TAG';
    public const OAUTH_CLIENT_AUTH_FAILED = 'UCP__OAUTH_CLIENT_AUTH_FAILED';
    public const OAUTH_CLIENT_NOT_FOUND = 'UCP__OAUTH_CLIENT_NOT_FOUND';
    public const OAUTH_BEARER_TOKEN_INVALID = 'UCP__OAUTH_BEARER_TOKEN_INVALID';
    public const A2A_PROTOCOL_ERROR = 'UCP__A2A_PROTOCOL_ERROR';
    public const JCS_CANONICALIZATION_FAILED = 'UCP__JCS_CANONICALIZATION_FAILED';
    public const LOYALTY_PROVIDER_ERROR = 'UCP__LOYALTY_PROVIDER_ERROR';
    public const MCP_TOOL_INVALID_ARGUMENTS = 'UCP__MCP_TOOL_INVALID_ARGUMENTS';
    public const TOKEN_ENTITY_INVALID = 'UCP__TOKEN_ENTITY_INVALID';

    public static function idempotencyKeyConflict(string $key): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::IDEMPOTENCY_KEY_CONFLICT,
            'Idempotency-Key "{{ key }}" was reused with a different request body. Use a unique key for each distinct write.',
            ['key' => $key]
        );
    }

    public static function idempotencyKeyRequired(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::IDEMPOTENCY_KEY_REQUIRED,
            'This UCP operation requires an `Idempotency-Key` header. See https://ucp.dev/specification/overview#idempotency.'
        );
    }

    public static function scopeRequired(string $requiredScope, ?string $providedScopes = null): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::SCOPE_REQUIRED,
            'This operation requires the OAuth scope "{{ scope }}". Granted: {{ granted }}.',
            ['scope' => $requiredScope, 'granted' => $providedScopes ?? '(none)']
        );
    }

    public static function signalsUntrusted(string $reason): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::SIGNALS_UNTRUSTED,
            'Platform signals were rejected: {{ reason }}',
            ['reason' => $reason]
        );
    }

    public static function invalidCursor(string $reason): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_CURSOR,
            'Invalid catalog cursor: {{ reason }}. Drop the cursor and restart pagination.',
            ['reason' => $reason]
        );
    }

    public static function invalidArgument(string $reason): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_ARGUMENT,
            'Invalid argument: {{ reason }}',
            ['reason' => $reason]
        );
    }

    public static function featureDisabled(): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::FEATURE_DISABLED,
            'UCP server feature is disabled.'
        );
    }

    public static function salesChannelNotConfigured(string $salesChannelId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::SALES_CHANNEL_NOT_CONFIGURED,
            'Sales channel "{{ salesChannelId }}" has no UCP configuration or UCP is inactive on it.',
            ['salesChannelId' => $salesChannelId]
        );
    }

    public static function capabilityNotEnabled(string $capability): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::CAPABILITY_NOT_ENABLED,
            'UCP capability "{{ capability }}" is not enabled for this sales channel.',
            ['capability' => $capability]
        );
    }

    public static function invalidProfileUrl(string $url): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_PROFILE_URL,
            'Platform profile URL "{{ url }}" is malformed, missing, or unresolvable.',
            ['url' => $url]
        );
    }

    public static function profileUnreachable(string $url, ?string $reason = null): self
    {
        return new self(
            Response::HTTP_FAILED_DEPENDENCY,
            self::PROFILE_UNREACHABLE,
            'Failed to fetch platform profile at "{{ url }}": {{ reason }}',
            ['url' => $url, 'reason' => $reason ?? 'unknown']
        );
    }

    public static function profileMalformed(string $url, string $reason): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::PROFILE_MALFORMED,
            'Platform profile at "{{ url }}" is malformed: {{ reason }}',
            ['url' => $url, 'reason' => $reason]
        );
    }

    public static function profileNamespaceMismatch(string $capability, string $expectedOrigin, string $actualOrigin): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::PROFILE_NAMESPACE_MISMATCH,
            'Capability "{{ capability }}" has spec origin "{{ actualOrigin }}" which does not match the namespace authority "{{ expectedOrigin }}".',
            ['capability' => $capability, 'expectedOrigin' => $expectedOrigin, 'actualOrigin' => $actualOrigin]
        );
    }

    public static function versionUnsupported(string $platformVersion, string $businessVersion): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::VERSION_UNSUPPORTED,
            'Protocol version "{{ platformVersion }}" is not supported. This business implements version "{{ businessVersion }}".',
            ['platformVersion' => $platformVersion, 'businessVersion' => $businessVersion]
        );
    }

    public static function versionInvalid(string $version): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::VERSION_INVALID,
            'Protocol version "{{ version }}" is not a valid YYYY-MM-DD string.',
            ['version' => $version]
        );
    }

    public static function capabilitiesIncompatible(): self
    {
        return new self(
            Response::HTTP_OK,
            self::CAPABILITIES_INCOMPATIBLE,
            'No mutually supported capabilities between platform and business.'
        );
    }

    public static function signatureMissing(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::SIGNATURE_MISSING,
            'Required HTTP Message Signature header is not present.'
        );
    }

    public static function signatureInvalid(string $detail = ''): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::SIGNATURE_INVALID,
            'HTTP Message Signature verification failed{{ detail }}.',
            ['detail' => $detail === '' ? '' : ': ' . $detail]
        );
    }

    public static function signatureKeyNotFound(string $kid): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::SIGNATURE_KEY_NOT_FOUND,
            'Signature key id "{{ kid }}" not found in signer\'s signing_keys.',
            ['kid' => $kid]
        );
    }

    public static function signatureAlgorithmUnsupported(string $algorithm): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SIGNATURE_ALGORITHM_UNSUPPORTED,
            'Signature algorithm "{{ algorithm }}" is not supported. Use ES256 or ES384.',
            ['algorithm' => $algorithm]
        );
    }

    public static function digestMismatch(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::DIGEST_MISMATCH,
            'Body digest does not match Content-Digest header.'
        );
    }

    public static function keyNotFound(string $kid, string $salesChannelId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::KEY_NOT_FOUND,
            'UCP signing key "{{ kid }}" not found for sales channel "{{ salesChannelId }}".',
            ['kid' => $kid, 'salesChannelId' => $salesChannelId]
        );
    }

    public static function keyGenerationFailed(string $reason): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::KEY_GENERATION_FAILED,
            'UCP signing key generation failed: {{ reason }}',
            ['reason' => $reason]
        );
    }

    /**
     * @param list<string> $supported
     */
    public static function unsupportedAlgorithm(string $algorithm, string $context, array $supported): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'UCP__UNSUPPORTED_ALGORITHM',
            'Unsupported algorithm "{{ algorithm }}" for {{ context }}. Supported: {{ supported }}.',
            ['algorithm' => $algorithm, 'context' => $context, 'supported' => implode(', ', $supported)]
        );
    }

    public static function keyDecryptionFailed(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::KEY_DECRYPTION_FAILED,
            'UCP private key could not be decrypted. Check that APP_SECRET has not been rotated without running ucp:keys:reencrypt.'
        );
    }

    public static function keyCannotBeDeleted(string $kid, string $status, ?\DateTimeInterface $retiringAt): self
    {
        $retiringAtIso = $retiringAt?->format(\DateTimeInterface::ATOM) ?? 'unknown';

        return new self(
            Response::HTTP_CONFLICT,
            self::KEY_CANNOT_BE_DELETED,
            'UCP signing key "{{ kid }}" cannot be deleted: current status "{{ status }}", retiringAt "{{ retiringAt }}". Active and retiring (<24h) keys cannot be deleted.',
            ['kid' => $kid, 'status' => $status, 'retiringAt' => $retiringAtIso]
        );
    }

    public static function encryptionFailed(string $reason): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ENCRYPTION_FAILED,
            'UCP key material encryption failed: {{ reason }}',
            ['reason' => $reason]
        );
    }

    public static function discoveryBudgetExceeded(): self
    {
        return new self(
            Response::HTTP_SERVICE_UNAVAILABLE,
            self::DISCOVERY_BUDGET_EXCEEDED,
            'Discovery budget exceeded — too many concurrent unknown platform profiles.'
        );
    }

    public static function capabilityTagInvalid(string $serviceId, string $reason): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CAPABILITY_INVALID_TAG,
            'Service "{{ serviceId }}" with tag `ucp.capability` is invalid: {{ reason }}',
            ['serviceId' => $serviceId, 'reason' => $reason]
        );
    }

    public static function mcpToolTagInvalid(string $serviceId, string $reason): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MCP_TOOL_INVALID_TAG,
            'Service "{{ serviceId }}" with tag `ucp.mcp_tool` is invalid: {{ reason }}',
            ['serviceId' => $serviceId, 'reason' => $reason]
        );
    }

    public static function paymentHandlerTagInvalid(string $serviceId, string $reason): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PAYMENT_HANDLER_INVALID_TAG,
            'Service "{{ serviceId }}" with tag `ucp.payment_handler` is invalid: {{ reason }}',
            ['serviceId' => $serviceId, 'reason' => $reason]
        );
    }

    public static function oauthClientAuthFailed(string $reason): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::OAUTH_CLIENT_AUTH_FAILED,
            'OAuth client authentication failed: {{ reason }}',
            ['reason' => $reason]
        );
    }

    public static function oauthClientNotFound(string $clientId): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::OAUTH_CLIENT_NOT_FOUND,
            'OAuth client "{{ clientId }}" was not found or is not active.',
            ['clientId' => $clientId]
        );
    }

    public static function oauthBearerTokenInvalid(string $reason): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::OAUTH_BEARER_TOKEN_INVALID,
            'Bearer token rejected: {{ reason }}',
            ['reason' => $reason]
        );
    }

    public static function a2aProtocolError(string $reason): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::A2A_PROTOCOL_ERROR,
            'A2A request rejected: {{ reason }}',
            ['reason' => $reason]
        );
    }

    public static function jcsCanonicalizationFailed(string $reason): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::JCS_CANONICALIZATION_FAILED,
            'JSON canonicalization (RFC 8785) failed: {{ reason }}',
            ['reason' => $reason]
        );
    }

    public static function loyaltyProviderError(string $reason): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::LOYALTY_PROVIDER_ERROR,
            'UCP loyalty provider error: {{ reason }}',
            ['reason' => $reason]
        );
    }

    public static function mcpToolInvalidArguments(string $tool, string $reason): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MCP_TOOL_INVALID_ARGUMENTS,
            'MCP tool "{{ tool }}" received invalid arguments: {{ reason }}',
            ['tool' => $tool, 'reason' => $reason]
        );
    }

    public static function tokenEntityInvalid(string $reason): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::TOKEN_ENTITY_INVALID,
            'OAuth token entity is in an invalid state: {{ reason }}',
            ['reason' => $reason]
        );
    }
}
