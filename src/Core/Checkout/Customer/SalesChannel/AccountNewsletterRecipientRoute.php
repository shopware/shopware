<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 *
 * @internal (flag:FEATURE_NEXT_14001) remove this comment on feature release
 */
class AccountNewsletterRecipientRoute extends AbstractAccountNewsletterRecipientRoute
{
    private SalesChannelRepositoryInterface $newsletterRecipientRepository;

    public function __construct(
        SalesChannelRepositoryInterface $newsletterRecipientRepository
    ) {
        $this->newsletterRecipientRepository = $newsletterRecipientRepository;
    }

    public function getDecorated(): AbstractAccountNewsletterRecipientRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.4.3.0")
     * @Entity("newsletter_recipient")
     * @OA\Post(
     *      path="/account/newsletter-recipient",
     *      summary="Fetch newsletter recipients",
     *      description="Perform a filtered search for newsletter recipients.",
     *      operationId="readNewsletterRecipient",
     *      tags={"Store API", "Profile", "Newsletter"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Response(
     *          response="200",
     *          description="",
     *          @OA\JsonContent(@OA\Items(ref="#/components/schemas/AccountNewsletterRecipientResult"))
     *     )
     * )
     * @LoginRequired()
     * @Route("/store-api/account/newsletter-recipient", name="store-api.newsletter.recipient", methods={"GET", "POST"})
     */
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria, CustomerEntity $customer): AccountNewsletterRecipientRouteResponse
    {
        $criteria->addFilter(new EqualsFilter('email', $customer->getEmail()));

        $result = $this->newsletterRecipientRepository->search($criteria, $context);

        return new AccountNewsletterRecipientRouteResponse($result);
    }
}
