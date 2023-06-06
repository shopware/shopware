<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api;

use Shopware\Core\Framework\Api\Exception\ExpectationFailedException;
use Shopware\Core\Framework\Api\Exception\InvalidSalesChannelIdException;
use Shopware\Core\Framework\Api\Exception\InvalidSyncOperationException;
use Shopware\Core\Framework\Api\Exception\InvalidVersionNameException;
use Shopware\Core\Framework\Api\Exception\LiveVersionDeleteException;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Api\Exception\NoEntityClonedException;
use Shopware\Core\Framework\Api\Exception\ResourceNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\MissingReverseAssociation;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Exception\SalesChannelNotFoundException;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException as SymfonyHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

#[Package('core')]
class ApiException extends HttpException
{
    public const API_INVALID_SYNC_CRITERIA_EXCEPTION = 'API_INVALID_SYNC_CRITERIA_EXCEPTION';
    public const API_RESOLVER_NOT_FOUND_EXCEPTION = 'API_RESOLVER_NOT_FOUND_EXCEPTION';

    public const API_UNSUPPORTED_ASSOCIATION_FIELD = 'FRAMEWORK__API_UNSUPPORTED_ASSOCIATION_FIELD_EXCEPTION';
    public const API_INVALID_SYNC_OPERATION_EXCEPTION = 'FRAMEWORK__INVALID_SYNC_OPERATION';

    public const API_NOT_EXISTING_RELATION_EXCEPTION = 'FRAMEWORK__NOT_EXISTING_RELATION_EXCEPTION';

    public const API_UNSUPPORTED_OPERATION_EXCEPTION = 'FRAMEWORK__UNSUPPORTED_OPERATION_EXCEPTION';
    public const API_INVALID_VERSION_ID = 'FRAMEWORK__INVALID_VERSION_ID';
    public const API_TYPE_PARAMETER_INVALID = 'FRAMEWORK__API_TYPE_PARAMETER_INVALID';
    public const API_APP_ID_PARAMETER_IS_MISSING = 'FRAMEWORK__APP_ID_PARAMETER_IS_MISSING';
    public const API_SALES_CHANNEL_ID_PARAMETER_IS_MISSING = 'FRAMEWORK__API_SALES_CHANNEL_ID_PARAMETER_IS_MISSING';
    public const API_CUSTOMER_ID_PARAMETER_IS_MISSING = 'FRAMEWORK__API_CUSTOMER_ID_PARAMETER_IS_MISSING';
    public const API_SHIPPING_COSTS_PARAMETER_IS_MISSING = 'FRAMEWORK__API_SHIPPING_COSTS_PARAMETER_IS_MISSING';
    public const API_UNABLE_GENERATE_BUNDLE = 'FRAMEWORK__API_UNABLE_GENERATE_BUNDLE';

    public static function invalidSyncCriteriaException(string $operationKey): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_INVALID_SYNC_CRITERIA_EXCEPTION,
            \sprintf('Sync operation %s, with action "delete", requires a criteria with at least one filter and can only be applied for mapping entities', $operationKey)
        );
    }

    public static function invalidSyncOperationException(string $message): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_INVALID_SYNC_OPERATION_EXCEPTION,
            $message
        );
    }

    public static function resolverNotFoundException(string $key): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_RESOLVER_NOT_FOUND_EXCEPTION,
            \sprintf('Foreign key resolver for key %s not found', $key)
        );
    }

    public static function unsupportedAssociation(string $field): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_UNSUPPORTED_ASSOCIATION_FIELD,
            'Unsupported association for field {{ field }}',
            ['field' => $field]
        );
    }

    /**
     * @param string[] $permissions
     */
    public static function missingPrivileges(array $permissions): ShopwareHttpException
    {
        return new MissingPrivilegeException($permissions);
    }

    public static function missingReverseAssociation(string $entity, string $parentEntity): ShopwareHttpException
    {
        return new MissingReverseAssociation($entity, $parentEntity);
    }

    public static function definitionNotFound(DefinitionNotFoundException $exception): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            $exception->getErrorCode(),
            $exception->getMessage(),
        );
    }

    public static function notExistingRelation(string $path): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::API_NOT_EXISTING_RELATION_EXCEPTION,
            'Resource at path "{{ path }}" is not an existing relation.',
            ['path' => $path]
        );
    }

    public static function unsupportedMediaType(string $contentType): SymfonyHttpException
    {
        return new UnsupportedMediaTypeHttpException(sprintf('The Content-Type "%s" is unsupported.', $contentType));
    }

    public static function badRequest(string $message): SymfonyHttpException
    {
        return new BadRequestHttpException($message);
    }

    /**
     * @param string[] $allow
     */
    public static function methodNotAllowed(array $allow, string $message): SymfonyHttpException
    {
        return new MethodNotAllowedHttpException($allow, $message);
    }

    public static function unauthorized(string $challenge, string $message): SymfonyHttpException
    {
        return new UnauthorizedHttpException($challenge, $message);
    }

    public static function noEntityCloned(string $entity, string $id): ShopwareHttpException
    {
        return new NoEntityClonedException($entity, $id);
    }

    /**
     * @param string[] $fails
     */
    public static function expectationFailed(array $fails): ShopwareHttpException
    {
        return new ExpectationFailedException($fails);
    }

    public static function invalidSyncOperation(string $message): ShopwareHttpException
    {
        return new InvalidSyncOperationException($message);
    }

    public static function invalidSalesChannelId(string $salesChannelId): ShopwareHttpException
    {
        return new InvalidSalesChannelIdException($salesChannelId);
    }

    public static function invalidVersionName(): ShopwareHttpException
    {
        return new InvalidVersionNameException();
    }

    public static function salesChannelNotFound(): ShopwareHttpException
    {
        return new SalesChannelNotFoundException();
    }

    public static function deleteLiveVersion(): ShopwareHttpException
    {
        return new LiveVersionDeleteException();
    }

    /**
     * @param array<mixed> $payload
     */
    public static function resourceNotFound(string $entity, array $payload): ShopwareHttpException
    {
        return new ResourceNotFoundException($entity, $payload);
    }

    public static function unsupportedOperation(string $operation): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_UNSUPPORTED_OPERATION_EXCEPTION,
            'Unsupported {{ operation }} operation.',
            ['operation' => $operation]
        );
    }

    public static function invalidVersionId(string $versionId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_INVALID_VERSION_ID,
            'versionId {{ versionId }} is not a valid uuid.',
            ['versionId' => $versionId]
        );
    }

    public static function invalidApiType(string $type): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_TYPE_PARAMETER_INVALID,
            'Parameter type {{ type }} is invalid.',
            ['type' => $type]
        );
    }

    public static function appIdParameterIsMissing(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_APP_ID_PARAMETER_IS_MISSING,
            'Parameter "id" is missing.',
        );
    }

    public static function salesChannelIdParameterIsMissing(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_SALES_CHANNEL_ID_PARAMETER_IS_MISSING,
            'Parameter "salesChannelId" is missing.',
        );
    }

    public static function customerIdParameterIsMissing(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_CUSTOMER_ID_PARAMETER_IS_MISSING,
            'Parameter "customerId" is missing.',
        );
    }

    public static function shippingCostsParameterIsMissing(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_SHIPPING_COSTS_PARAMETER_IS_MISSING,
            'Parameter "shippingCosts" is missing.',
        );
    }

    public static function unableGenerateBundle(string $bundleName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::API_UNABLE_GENERATE_BUNDLE,
            'Unable to generate bundle directory for bundle "{{ bundleName }}".',
            ['bundleName' => $bundleName]
        );
    }
}
