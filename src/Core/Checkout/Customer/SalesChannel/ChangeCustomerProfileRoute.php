<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Event\DataMappingEvent;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\ContextTokenRequired;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @RouteScope(scopes={"store-api"})
 * @ContextTokenRequired()
 */
class ChangeCustomerProfileRoute extends AbstractChangeCustomerProfileRoute
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

    /**
     * @var DataValidationFactoryInterface
     */
    private $customerProfileValidationFactory;

    public function __construct(
        EntityRepositoryInterface $customerRepository,
        EventDispatcherInterface $eventDispatcher,
        DataValidator $validator,
        DataValidationFactoryInterface $customerProfileValidationFactory
    ) {
        $this->customerRepository = $customerRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->validator = $validator;
        $this->customerProfileValidationFactory = $customerProfileValidationFactory;
    }

    public function getDecorated(): AbstractChangeCustomerProfileRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.2.0.0")
     * @OA\Post(
     *      path="/account/change-profile",
     *      summary="Change profile information",
     *      operationId="changeProfile",
     *      tags={"Store API", "Account"},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(property="salutationId", description="Salutation ID", type="string"),
     *              @OA\Property(property="firstName", description="Firstname", type="string"),
     *              @OA\Property(property="lastName", description="Firstname", type="string")
     *          )
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Successfully saved",
     *          @OA\JsonContent(ref="#/components/schemas/SuccessResponse")
     *     )
     * )
     * @LoginRequired()
     * @Route(path="/store-api/account/change-profile", name="store-api.account.change-profile", methods={"POST"})
     */
    public function change(RequestDataBag $data, SalesChannelContext $context, CustomerEntity $customer): SuccessResponse
    {
        $validation = $this->customerProfileValidationFactory->update($context);

        if ($data->get('accountType') === CustomerEntity::ACCOUNT_TYPE_BUSINESS) {
            $validation->add('company', new NotBlank());
        } else {
            $data->set('company', '');
            $data->set('vatIds', null);
        }

        $this->dispatchValidationEvent($validation, $data, $context->getContext());

        $this->validator->validate($data->all(), $validation);

        $customerData = $data->only('firstName', 'lastName', 'salutationId', 'title', 'company');

        if ($vatIds = $data->get('vatIds')) {
            $customerData['vatIds'] = empty($vatIds->all()) ? null : $vatIds->all();
        }

        if ($birthday = $this->getBirthday($data)) {
            $customerData['birthday'] = $birthday;
        }

        $mappingEvent = new DataMappingEvent($data, $customerData, $context->getContext());
        $this->eventDispatcher->dispatch($mappingEvent, CustomerEvents::MAPPING_CUSTOMER_PROFILE_SAVE);

        $customerData = $mappingEvent->getOutput();

        $customerData['id'] = $customer->getId();

        $this->customerRepository->update([$customerData], $context->getContext());

        return new SuccessResponse();
    }

    private function dispatchValidationEvent(DataValidationDefinition $definition, DataBag $data, Context $context): void
    {
        $validationEvent = new BuildValidationEvent($definition, $data, $context);
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());
    }

    private function getBirthday(DataBag $data): ?\DateTimeInterface
    {
        $birthdayDay = $data->get('birthdayDay');
        $birthdayMonth = $data->get('birthdayMonth');
        $birthdayYear = $data->get('birthdayYear');

        if (!$birthdayDay || !$birthdayMonth || !$birthdayYear) {
            return null;
        }

        return new \DateTime(sprintf(
            '%s-%s-%s',
            $birthdayYear,
            $birthdayMonth,
            $birthdayDay
        ));
    }
}
