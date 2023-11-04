<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\Constraint\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: ['_routeScope' => ['store-api'], '_contextTokenRequired' => true])]
#[Package('customer-order')]
class ChangeLanguageRoute extends AbstractChangeLanguageRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $customerRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DataValidator $validator
    ) {
    }

    public function getDecorated(): AbstractChangeLanguageRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/account/change-language', name: 'store-api.account.change-language', methods: ['POST'], defaults: ['_loginRequired' => true])]
    public function change(RequestDataBag $requestDataBag, SalesChannelContext $context, CustomerEntity $customer): SuccessResponse
    {
        $this->validateLanguageId($requestDataBag, $context);

        $customerData = [
            'id' => $customer->getId(),
            'languageId' => $requestDataBag->get('languageId'),
        ];

        $this->customerRepository->update([$customerData], $context->getContext());

        return new SuccessResponse();
    }

    private function validateLanguageId(DataBag $data, SalesChannelContext $context): void
    {
        $validation = new DataValidationDefinition('customer.language.update');

        $languageCriteria = new Criteria([$data->get('languageId')]);
        $languageCriteria->addFilter(new EqualsFilter('salesChannels.id', $context->getSalesChannelId()));

        $validation->add('languageId', new Uuid())
            ->add('languageId', new EntityExists(['entity' => 'language', 'context' => $context->getContext(), 'criteria' => $languageCriteria]));

        $this->dispatchValidationEvent($validation, $data, $context->getContext());

        $this->validator->validate($data->all(), $validation);
    }

    private function dispatchValidationEvent(DataValidationDefinition $definition, DataBag $data, Context $context): void
    {
        $validationEvent = new BuildValidationEvent($definition, $data, $context);
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());
    }
}
