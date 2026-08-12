<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\NewsletterRecipient\NewsletterStatus;
use Shopware\Core\Checkout\Customer\SalesChannel\NewsletterRecipient\ReadNewsletterRecipientExtension;
use Shopware\Core\Checkout\Customer\SalesChannel\NewsletterRecipient\ReadNewsletterRecipientResponse;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientCollection;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class AccountNewsletterRecipientRoute extends AbstractAccountNewsletterRecipientRoute
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

    public function getDecorated(): AbstractAccountNewsletterRecipientRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/account/newsletter-recipient',
        name: 'store-api.newsletter.recipient',
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_ENTITY => NewsletterRecipientDefinition::ENTITY_NAME,
        ],
        methods: [Request::METHOD_GET, Request::METHOD_POST]
    )]
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria, CustomerEntity $customer): AccountNewsletterRecipientRouteResponse
    {
        $criteria->addFilter(new EqualsFilter('email', $customer->getEmail()));

        $result = $this->newsletterRecipientRepository->search($criteria, $context);

        return new AccountNewsletterRecipientRouteResponse($result);
    }

    #[Route(
        path: '/store-api/v2/account/newsletter-recipient',
        name: 'store-api.newsletter.recipient.v2',
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function loadV2(
        SalesChannelContext $context,
        CustomerEntity $customer
    ): ReadNewsletterRecipientResponse {
        return $this->extensions->publish(
            name: ReadNewsletterRecipientExtension::NAME,
            extension: new ReadNewsletterRecipientExtension(new Criteria(), $context, $customer),
            function: $this->loadV2Internal(...),
        );
    }

    private function loadV2Internal(
        Criteria $criteria,
        SalesChannelContext $context,
        CustomerEntity $customer,
    ): ReadNewsletterRecipientResponse {
        $criteria->addFilter(new EqualsFilter('email', $customer->getEmail()));
        $criteria->setLimit(1);
        $criteria->addFields(['status']);

        $status = $this->newsletterRecipientRepository->search($criteria, $context)->getEntities()->first()?->get('status') ?? self::UNDEFINED;

        return new ReadNewsletterRecipientResponse(NewsletterStatus::from($status));
    }
}
