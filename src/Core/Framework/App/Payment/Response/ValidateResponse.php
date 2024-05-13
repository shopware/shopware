<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Response;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class ValidateResponse extends AbstractResponse
{
    /**
     * This will be sent with the capture call for the app to identify the verified payment
     *
     * @var mixed[]
     */
    protected array $preOrderPayment = [];

    /**
     * @return mixed[]
     */
    public function getPreOrderPayment(): array
    {
        return $this->preOrderPayment;
    }
}
