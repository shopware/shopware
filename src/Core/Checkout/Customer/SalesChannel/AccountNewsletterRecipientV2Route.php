<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\NewsletterRecipient\NewsletterStatus;
use Shopware\Core\Checkout\Customer\SalesChannel\NewsletterRecipient\ReadNewsletterRecipientExtension;
use Shopware\Core\Checkout\Customer\SalesChannel\NewsletterRecipient\ReadNewsletterRecipientStatusResponse;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class AccountNewsletterRecipientV2Route
{
    final public const UNDEFINED = 'undefined';

    /**
     * @internal
     *
     * @param SalesChannelRepository<NewsletterRecipientCollection> $newsletterRecipientRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $newsletterRecipientRepository,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    #[Route(
        path: '/store-api/account/newsletter-recipient-v2',
        name: 'store-api.newsletter.recipient.v2',
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function loadV2(
        SalesChannelContext $context,
        CustomerEntity $customer
    ): ReadNewsletterRecipientStatusResponse {
        return $this->extensions->publish(
            name: ReadNewsletterRecipientExtension::NAME,
            extension: new ReadNewsletterRecipientExtension(
                (new Criteria())
                    ->addFilter(new EqualsFilter('email', $customer->getEmail()))
                    ->setLimit(1)
                    ->addFields(['status']),
                $context,
                $customer,
            ),
            function: fn (Criteria $criteria, SalesChannelContext $context, CustomerEntity $customer): ReadNewsletterRecipientStatusResponse => $this->loadV2Internal($criteria, $context),
        );
    }

    private function loadV2Internal(
        Criteria $criteria,
        SalesChannelContext $context,
    ): ReadNewsletterRecipientStatusResponse {
        $status = $this->newsletterRecipientRepository->search($criteria, $context)->getEntities()->first()?->get('status') ?? self::UNDEFINED;

        return new ReadNewsletterRecipientStatusResponse(NewsletterStatus::from($status));
    }
}
