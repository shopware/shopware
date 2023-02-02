<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContactForm\SalesChannel;

use Shopware\Core\Framework\Struct\Struct;

class ContactFormRouteResponseStruct extends Struct
{
    /**
     * @var string
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
