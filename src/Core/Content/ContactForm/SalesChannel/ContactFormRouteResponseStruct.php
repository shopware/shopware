<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContactForm\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('buyers-experience')]
class ContactFormRouteResponseStruct extends Struct
{
    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $individualSuccessMessage;

    public function getApiAlias(): string
    {
        return 'contact_form_result';
    }

    public function getIndividualSuccessMessage(): string
    {
        return $this->individualSuccessMessage;
    }
}
