<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContactForm;

use Shopware\Core\Content\ContactForm\SalesChannel\AbstractContactFormRoute;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/** @deprecated tag:v6.3.0 use Shopware\Core\Content\ContactForm\SalesChannel\AbstractContactFormRoute */
class ContactFormService
{
    /**
     * @var AbstractContactFormRoute
     */
    private $contactFormRoute;

    public function __construct(
        AbstractContactFormRoute $contactFormRoute
    ) {
        $this->contactFormRoute = $contactFormRoute;
    }

    public function sendContactForm(DataBag $data, SalesChannelContext $context): string
    {
        return $this->contactFormRoute->load($data->toRequestDataBag(), $context)->getResult()->getIndividualSuccessMessage();
    }
}
