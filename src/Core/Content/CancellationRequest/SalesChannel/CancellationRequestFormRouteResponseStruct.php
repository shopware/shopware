<?php declare(strict_types=1);

namespace Shopware\Core\Content\CancellationRequest\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('after-sales')]
class CancellationRequestFormRouteResponseStruct extends Struct
{
    protected string $individualSuccessMessage;

    public function getApiAlias(): string
    {
        return 'cancellation_request_form_result';
    }

    public function getIndividualSuccessMessage(): string
    {
        return $this->individualSuccessMessage;
    }
}
