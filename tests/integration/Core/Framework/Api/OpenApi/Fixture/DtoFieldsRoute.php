<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\OpenApi\Fixture;

use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
final class DtoFieldsRoute
{
    public function __construct(private readonly ExtensionDispatcher $extensions)
    {
    }

    #[Route(
        path: '/store-api/test/dto-fields',
        name: 'store-api.test.dto-fields',
        methods: [Request::METHOD_POST],
    )]
    public function transform(
        Request $request,
        #[MapRequestPayload]
        TransformFieldsRequest $payload,
        SalesChannelContext $context
    ): TransformFieldsResponse {
        return $this->extensions->publish(
            name: DtoFieldsRouteExtension::NAME,
            extension: new DtoFieldsRouteExtension($request, $payload, $context),
            function: fn (Request $request, TransformFieldsRequest $payload, SalesChannelContext $context): TransformFieldsResponse => $this->_transform($payload),
        );
    }

    private function _transform(TransformFieldsRequest $payload): TransformFieldsResponse
    {
        $response = $this->createResponseFromRequiredFields($payload);
        $this->fillOptionalScalarFields($payload, $response);
        $this->fillOptionalNullableFields($payload, $response);

        return $response;
    }

    private function createResponseFromRequiredFields(TransformFieldsRequest $payload): TransformFieldsResponse
    {
        $requiredScalar = $payload->requiredScalar;
        $requiredScalarWithDefault = $payload->has('requiredScalarWithDefault') ? $payload->requiredScalarWithDefault : TransformFieldsRequest::DEFAULT_REQUIRED_SCALAR_WITH_DEFAULT;
        $requiredNullable = $payload->requiredNullable;
        $requiredNullableWithDefault = $payload->has('requiredNullableWithDefault') ? $payload->requiredNullableWithDefault : TransformFieldsRequest::DEFAULT_REQUIRED_NULLABLE_WITH_DEFAULT;

        $requiredScalar = strtoupper($requiredScalar);
        $requiredScalarWithDefault = strtoupper($requiredScalarWithDefault);
        $requiredNullable = $requiredNullable === null ? null : strtoupper($requiredNullable);
        $requiredNullableWithDefault = $requiredNullableWithDefault === null ? null : strtoupper($requiredNullableWithDefault);

        return new TransformFieldsResponse(
            $requiredScalar,
            $requiredScalarWithDefault,
            $requiredNullable,
            $requiredNullableWithDefault,
        );
    }

    private function fillOptionalScalarFields(TransformFieldsRequest $payload, TransformFieldsResponse $response): void
    {
        if ($payload->has('optionalScalar')) {
            $optionalScalar = $payload->optionalScalar;
            $optionalScalar = strtoupper($optionalScalar);
            $response->optionalScalar = $optionalScalar;
        }

        $optionalScalarWithDefault = $payload->has('optionalScalarWithDefault') ? $payload->optionalScalarWithDefault : TransformFieldsRequest::DEFAULT_OPTIONAL_SCALAR_WITH_DEFAULT;
        $optionalScalarWithDefault = strtoupper($optionalScalarWithDefault);
        $response->optionalScalarWithDefault = $optionalScalarWithDefault;
    }

    private function fillOptionalNullableFields(TransformFieldsRequest $payload, TransformFieldsResponse $response): void
    {
        if ($payload->has('optionalNullable')) {
            $optionalNullable = $payload->optionalNullable;
            $optionalNullable = $optionalNullable === null ? null : strtoupper($optionalNullable);
            $response->optionalNullable = $optionalNullable;
        }

        $optionalNullableWithDefault = $payload->has('optionalNullableWithDefault') ? $payload->optionalNullableWithDefault : TransformFieldsRequest::DEFAULT_OPTIONAL_NULLABLE_WITH_DEFAULT;
        $optionalNullableWithDefault = $optionalNullableWithDefault === null ? null : strtoupper($optionalNullableWithDefault);
        $response->optionalNullableWithDefault = $optionalNullableWithDefault;
    }
}
