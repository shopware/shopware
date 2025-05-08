<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * @extends StoreApiResponse<ArrayStruct<array{redirectResponse: RedirectResponse|null}>>
 */
#[Package('checkout')]
class HandlePaymentMethodRouteResponse extends StoreApiResponse
{
    public function __construct(?RedirectResponse $response)
    {
        parent::__construct(
            new ArrayStruct(['redirectResponse' => $response])
        );
    }

    public function getRedirectResponse(): ?RedirectResponse
    {
        return $this->object->get('redirectResponse');
    }

    /**
     * @return ArrayStruct<array{redirectUrl: string|null}>
     *
     * @phpstan-ignore method.childReturnType (it is intended to return a different ArrayStruct)
     */
    public function getObject(): ArrayStruct
    {
        return new ArrayStruct([
            'redirectUrl' => $this->getRedirectResponse()?->getTargetUrl(),
        ]);
    }
}
