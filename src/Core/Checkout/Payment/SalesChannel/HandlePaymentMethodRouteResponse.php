<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\SalesChannel;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

class HandlePaymentMethodRouteResponse extends StoreApiResponse
{
    /**
     * @var ArrayStruct
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
