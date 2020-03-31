<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Checkout\Customer\Exception\CustomerAlreadyConfirmedException;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundByHashException;
use Shopware\Core\Checkout\Customer\Exception\NoHashProvidedException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @RouteScope(scopes={"store-api"})
 */
class RegisterConfirmRoute extends AbstractRegisterConfirmRoute
{
    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var DataValidator
     */
    private $validator;

    public function __construct(
        EntityRepositoryInterface $customerRepository,
        EventDispatcherInterface $eventDispatcher,
        DataValidator $validator
    ) {
        $this->customerRepository = $customerRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->validator = $validator;
    }

    public function getDecorated(): AbstractRegisterConfirmRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @OA\Post(
     *      path="/account/register-confirm",
     *      description="Confirm double optin registration",
     *      operationId="registerConfirm",
     *      tags={"Store API", "Account"},
     *      @OA\Parameter(name="hash", description="Hash from Link in Mail", in="query", @OA\Schema(type="string")),
     *      @OA\Parameter(name="em", description="em from Link in Mail", in="query", @OA\Schema(type="string")),
     *      @OA\Response(
     *          response="200",
     *          description="Success"
     *     )
     * )
     * @Route("/store-api/v{version}/account/register-confirm", name="store-api.account.register.confirm", methods={"POST"})
     */
    public function confirm(RequestDataBag $dataBag, SalesChannelContext $context): CustomerResponse
    {
        if (!$dataBag->has('hash')) {
            throw new NoHashProvidedException();
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('hash', $dataBag->get('hash')));
        $criteria->addAssociation('addresses');
        $criteria->addAssociation('salutation');
        $criteria->setLimit(1);

        $customer = $this->customerRepository
            ->search($criteria, $context->getContext())
            ->first();

        if ($customer === null) {
            throw new CustomerNotFoundByHashException($dataBag->get('hash'));
        }

        $this->validator->validate(
            ['em' => $dataBag->get('em')],
            $this->getBeforeConfirmValidation(hash('sha1', $customer->getEmail()))
        );

        if ($customer->getActive()) {
            throw new CustomerAlreadyConfirmedException($customer->getId());
        }

        $this->customerRepository->update(
            [
                [
                    'id' => $customer->getId(),
                    'active' => true,
                    'doubleOptInConfirmDate' => new \DateTimeImmutable(),
                ],
            ],
            $context->getContext()
        );

        if (!$customer->getGuest()) {
            $event = new CustomerRegisterEvent($context, $customer);

            $this->eventDispatcher->dispatch($event);
        }

        return new CustomerResponse($customer);
    }

    private function getBeforeConfirmValidation(string $emHash): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('registration.opt_in_before');
        $definition->add('em', new EqualTo(['value' => $emHash]));

        return $definition;
    }
}
