<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Exception\BadCredentialsException;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundException;
use Shopware\Core\Checkout\Customer\Exception\InactiveCustomerException;
use Shopware\Core\Checkout\Customer\Password\LegacyPasswordVerifier;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\ContextTokenRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextRestorer;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @RouteScope(scopes={"store-api"})
 * @ContextTokenRequired()
 */
class LoginRoute extends AbstractLoginRoute
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var SalesChannelContextPersister
     */
    private $contextPersister;

    /**
     * @var LegacyPasswordVerifier
     */
    private $legacyPasswordVerifier;

    /**
     * @var SalesChannelContextRestorer|null
     */
    private $contextRestorer;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        SalesChannelContextPersister $contextPersister,
        EntityRepositoryInterface $customerRepository,
        LegacyPasswordVerifier $legacyPasswordVerifier,
        ?SalesChannelContextRestorer $contextRestorer
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->contextPersister = $contextPersister;
        $this->customerRepository = $customerRepository;
        $this->legacyPasswordVerifier = $legacyPasswordVerifier;
        $this->contextRestorer = $contextRestorer;
    }

    public function getDecorated(): AbstractLoginRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @OA\Post(
     *      path="/account/login",
     *      description="Login as customer using password",
     *      operationId="loginCustomer",
     *      tags={"Store API", "Account"},
     *      @OA\Parameter(
     *          parameter="username",
     *          name="Email",
     *          in="body",
     *          description="Email",
     *          @OA\Schema(type="string"),
     *      ),
     *      @OA\Parameter(
     *          parameter="password",
     *          name="Password",
     *          in="body",
     *          description="Password",
     *          @OA\Schema(type="string"),
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Context token",
     *          @OA\JsonContent(ref="#/definitions/ContextTokenResponse")
     *     )
     * )
     * @Route(path="/store-api/v{version}/account/login", name="store-api.account.login", methods={"POST"})
     */
    public function login(RequestDataBag $data, SalesChannelContext $context): ContextTokenResponse
    {
        $email = $data->get('email', $data->get('username'));

        if (empty($email) || empty($data->get('password'))) {
            throw new BadCredentialsException();
        }

        $event = new CustomerBeforeLoginEvent($context, $email);
        $this->eventDispatcher->dispatch($event);

        try {
            $customer = $this->getCustomerByLogin(
                $email,
                $data->get('password'),
                $context
            );
        } catch (CustomerNotFoundException | BadCredentialsException $exception) {
            throw new UnauthorizedHttpException('json', $exception->getMessage());
        }

        if (!$customer->getActive()) {
            throw new InactiveCustomerException($customer->getId());
        }

        if (Feature::isActive('FEATURE_NEXT_10058') && $this->contextRestorer) {
            $context = $this->contextRestorer->restore($customer->getId(), $context);
            $newToken = $context->getToken();
        } else {
            $newToken = $this->contextPersister->replace($context->getToken(), $context);

            $this->contextPersister->save(
                $newToken,
                [
                    'customerId' => $customer->getId(),
                    'billingAddressId' => null,
                    'shippingAddressId' => null,
                ]
            );
        }

        $this->customerRepository->update([
            [
                'id' => $customer->getId(),
                'lastLogin' => new \DateTimeImmutable(),
            ],
        ], $context->getContext());

        $event = new CustomerLoginEvent($context, $customer, $newToken);
        $this->eventDispatcher->dispatch($event);

        return new ContextTokenResponse($newToken);
    }

    private function getCustomerByLogin(string $email, string $password, SalesChannelContext $context): CustomerEntity
    {
        $customer = $this->getCustomerByEmail($email, $context);

        if ($customer->hasLegacyPassword()) {
            if (!$this->legacyPasswordVerifier->verify($password, $customer)) {
                throw new BadCredentialsException();
            }

            $this->updatePasswordHash($password, $customer, $context->getContext());

            return $customer;
        }

        if (!password_verify($password, $customer->getPassword())) {
            throw new BadCredentialsException();
        }

        return $customer;
    }

    private function getCustomerByEmail(string $email, SalesChannelContext $context): CustomerEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customer.email', $email));
        $criteria->addFilter(new EqualsFilter('customer.guest', 0));

        $result = $this->customerRepository->search($criteria, $context->getContext());

        if ($result->count() !== 1) {
            throw new BadCredentialsException();
        }

        return $result->first();
    }

    private function updatePasswordHash(string $password, CustomerEntity $customer, Context $context): void
    {
        $this->customerRepository->update([
            [
                'id' => $customer->getId(),
                'password' => $password,
                'legacyPassword' => null,
                'legacyEncoder' => null,
            ],
        ], $context);
    }
}
