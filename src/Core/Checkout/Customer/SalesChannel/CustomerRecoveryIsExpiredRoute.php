<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundByHashException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
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

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('customer-order')]
class CustomerRecoveryIsExpiredRoute extends AbstractCustomerRecoveryIsExpiredRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $customerRecoveryRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DataValidator $validator
    ) {
    }

    public function getDecorated(): AbstractResetPasswordRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/account/customer-recovery-is-expired', name: 'store-api.account.customer.recovery.is.expired', methods: ['POST'])]
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
