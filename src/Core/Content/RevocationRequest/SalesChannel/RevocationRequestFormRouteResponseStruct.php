<?php declare(strict_types=1);

namespace Shopware\Core\Content\RevocationRequest\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('after-sales')]
class RevocationRequestFormRouteResponseStruct extends Struct
{
    protected string $individualSuccessMessage;

    public function getApiAlias(): string
    {
        return 'revocation_request_form_result';
    }

    public function getIndividualSuccessMessage(): string
    {
        return $this->individualSuccessMessage;
    }
}
