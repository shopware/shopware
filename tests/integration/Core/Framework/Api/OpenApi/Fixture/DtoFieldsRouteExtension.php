<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\OpenApi\Fixture;

use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @extends Extension<TransformFieldsResponse>
 *
 * @phpstan-ignore shopware.internalClass (Extension events must remain public, including integration fixtures)
 */
#[Package('framework')]
final class DtoFieldsRouteExtension extends Extension
{
    public const NAME = 'test.dto-fields.transform';

    /**
     * @internal
     */
    public function __construct(
        public readonly Request $request,
        public readonly TransformFieldsRequest $payload,
        public readonly SalesChannelContext $context,
    ) {
    }
}
