<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundByHashException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\ContextTokenRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @Route(defaults={"_routeScope"={"store-api"}, "_contextTokenRequired"=true})
 */
class CustomerRecoveryIsExpiredRoute extends AbstractCustomerRecoveryIsExpiredRoute
{
    private EntityRepository $customerRecoveryRepository;

    private EventDispatcherInterface $eventDispatcher;

    private DataValidator $validator;

    /**
     * @internal
     */
    public function __construct(
        EntityRepository $customerRecoveryRepository,
        EventDispatcherInterface $eventDispatcher,
        DataValidator $validator
    ) {
        $this->customerRecoveryRepository = $customerRecoveryRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->validator = $validator;
    }

    public function getDecorated(): AbstractResetPasswordRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.4.14.0")
     * @OA\Post(
     *      path="/account/customer-recovery-is-expired",
     *      summary="Checks if the customer recovery entry for a given hash is expired.",
     *      description="This can be used to validate a provided hash has a valid and not expired customer recovery hash.",
     *      operationId="getCustomerRecoveryIsExpired",
     *      tags={"Store API", "Profile"},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={
     *                  "hash"
     *              },
     *              @OA\Property(
     *                  property="hash",
     *                  description="Parameter from the link in the confirmation mail sent in Step 1",
     *                  type="string")
     *          )
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Returns a CustomerRecoveryIsExpiredResponse that indicates if the hash is expired or not.",
     *          @OA\JsonContent(ref="#/components/schemas/ArrayStruct")
     *     )
     * )
     * @Route(path="/store-api/account/customer-recovery-is-expired", name="store-api.account.customer.recovery.is.expired", methods={"POST"})
     */
    public function load(RequestDataBag $data, SalesChannelContext $context): CustomerRecoveryIsExpiredResponse
    {
        $this->validateHash($data, $context);

        $hash = $data->get('hash');

        $customerHashCriteria = new Criteria();
        $customerHashCriteria->addFilter(new EqualsFilter('hash', $hash));

        $customerRecovery = $this->customerRecoveryRepository->search(
            $customerHashCriteria,
            $context->getContext()
        )->first();

        if (!$customerRecovery) {
            throw new CustomerNotFoundByHashException($hash);
        }

        return new CustomerRecoveryIsExpiredResponse($this->isExpired($customerRecovery));
    }

    /**
     * @throws ConstraintViolationException
     */
    private function validateHash(DataBag $data, SalesChannelContext $context): void
    {
        $definition = new DataValidationDefinition('customer.recovery.get');

        $hashLength = 32;

        $definition->add('hash', new NotBlank(), new Type('string'), new Length($hashLength));

        $this->dispatchValidationEvent($definition, $data, $context->getContext());

        $this->validator->validate($data->all(), $definition);
    }

    private function dispatchValidationEvent(DataValidationDefinition $definition, DataBag $data, Context $context): void
    {
        $validationEvent = new BuildValidationEvent($definition, $data, $context);
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());
    }

    private function isExpired(CustomerRecoveryEntity $customerRecovery): bool
    {
        $validDateTime = (new \DateTime())->sub(new \DateInterval('PT2H'));

        return $validDateTime > $customerRecovery->getCreatedAt();
    }
}
