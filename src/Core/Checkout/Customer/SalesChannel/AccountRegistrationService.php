<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Content\Newsletter\Exception\SalesChannelDomainNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class AccountRegistrationService
{
    /**
     * @var AbstractRegisterRoute
     */
    private $registerRoute;

    /**
     * @var AbstractRegisterConfirmRoute
     */
    private $registerConfirmRoute;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var EntityRepositoryInterface
     */
    private $domainRepository;

    public function __construct(
        AbstractRegisterRoute $registerRoute,
        AbstractRegisterConfirmRoute $registerConfirmRoute,
        EntityRepositoryInterface $domainRepository,
        SystemConfigService $systemConfigService
    ) {
        $this->registerRoute = $registerRoute;
        $this->registerConfirmRoute = $registerConfirmRoute;
        $this->domainRepository = $domainRepository;
        $this->systemConfigService = $systemConfigService;
    }

    public function register(DataBag $data, bool $isGuest, SalesChannelContext $context, ?DataValidationDefinition $additionalValidationDefinitions = null): string
    {
        if ($isGuest) {
            $data->set('guest', $isGuest);
        }

        if (!$data->has('storefrontUrl')) {
            $data->set('storefrontUrl', $this->getConfirmUrl($context));
        }

        return $this->registerRoute
            ->register($data->toRequestDataBag(), $context, false, $additionalValidationDefinitions)
            ->getCustomer()
            ->getId();
    }

    public function finishDoubleOptInRegistration(DataBag $dataBag, SalesChannelContext $context): string
    {
        return $this->registerConfirmRoute
            ->confirm($dataBag->toRequestDataBag(), $context)
            ->getCustomer()
            ->getId();
    }

    private function getConfirmUrl(SalesChannelContext $context): string
    {
        /** @var string $domainUrl */
        $domainUrl = $this->systemConfigService
            ->get('core.loginRegistration.doubleOptInDomain', $context->getSalesChannel()->getId());

        if (!$domainUrl) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('salesChannelId', $context->getSalesChannel()->getId()));
            $criteria->setLimit(1);

            $domain = $this->domainRepository
                ->search($criteria, $context->getContext())
                ->first();

            if (!$domain) {
                throw new SalesChannelDomainNotFoundException($context->getSalesChannel());
            }

            $domainUrl = $domain->getUrl();
        }

        return $domainUrl;
    }
}
