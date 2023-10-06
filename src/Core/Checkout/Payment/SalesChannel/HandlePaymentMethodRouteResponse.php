<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

#[Package('checkout')]
class HandlePaymentMethodRouteResponse extends StoreApiResponse
{
    /**
     * @var ArrayStruct<string, mixed>
     */
    protected $object;

    public function __construct(?RedirectResponse $response)
    {
        parent::__construct(
            new ArrayStruct(
                [
                    'redirectResponse' => $response,
                ]
            )
        );
    }

    public function getRedirectResponse(): ?RedirectResponse
    {
        return $this->object->get('redirectResponse');
    }

    public function getObject(): Struct
    {
        return new ArrayStruct([
            'redirectUrl' => $this->getRedirectResponse() ? $this->getRedirectResponse()->getTargetUrl() : null,
        ]);
    }
}
