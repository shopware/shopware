<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContactForm;

use Shopware\Core\Content\ContactForm\SalesChannel\ContactFormRouteInterface;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/** @deprecated tag:v6.3.0 use Shopware\Core\Content\ContactForm\SalesChannel\ContactFormRouteInterface */
class ContactFormService
{
    /**
     * @var ContactFormRouteInterface
     */
    private $contactFormRoute;

    public function __construct(
        ContactFormRouteInterface $contactFormRoute
    ) {
        $this->contactFormRoute = $contactFormRoute;
    }

    public function sendContactForm(DataBag $data, SalesChannelContext $context): string
    {
        $data = new RequestDataBag($data->all());

        return $this->contactFormRoute->load($data, $context)->getResult()->getIndividualSuccessMessage();
    }
}
