<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Gateway\Context\Command\Handler;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeShippingLocationCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('framework')]
class ChangeShippingLocationCommandHandler extends AbstractContextGatewayCommandHandler
{
    public function __construct(
        private readonly EntityRepository $countryRepository,
        private readonly EntityRepository $countryStateRepository,
    ) {
    }

    /**
     * @param ChangeShippingLocationCommand $command
     */
    public function handle(AbstractContextGatewayCommand $command, SalesChannelContext $context, array &$parameters): void
    {
        if ($command->countryIso !== null) {
            $criteria = new Criteria();
            $criteria->addFilter(
                new OrFilter(
                    [
                        new EqualsFilter('country.iso', $command->countryIso),
                        new EqualsFilter('country.iso3', $command->countryIso),
                    ]
                )
            );

            $countryId = $this->countryRepository->searchIds($criteria, $context->getContext())->firstId();

            if ($countryId === null) {
                return;
            }

            $parameters['countryId'] = $countryId;
        }

        if ($command->countryStateIso !== null) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('shortCode', $command->countryStateIso));

            $stateId = $this->countryStateRepository->searchIds($criteria, $context->getContext())->firstId();

            if ($stateId === null) {
                return;
            }

            $parameters['countryStateId'] = $stateId;
        }
    }

    public static function supportedCommands(): array
    {
        return [ChangeShippingLocationCommand::class];
    }
}
