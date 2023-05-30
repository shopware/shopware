<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('customer-order')]
class AccountNewsletterRecipientRoute extends AbstractAccountNewsletterRecipientRoute
{
    /**
     * @internal
     */
    public function __construct(private readonly SalesChannelRepository $newsletterRecipientRepository)
    {
    }

    public function getDecorated(): AbstractAccountNewsletterRecipientRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/account/newsletter-recipient', name: 'store-api.newsletter.recipient', methods: ['GET', 'POST'], defaults: ['_loginRequired' => true, '_entity' => 'newsletter_recipient'])]
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria, CustomerEntity $customer): AccountNewsletterRecipientRouteResponse
    {
        $criteria->addFilter(new EqualsFilter('email', $customer->getEmail()));

        $result = $this->newsletterRecipientRepository->search($criteria, $context);

        return new AccountNewsletterRecipientRouteResponse($result);
    }
}
