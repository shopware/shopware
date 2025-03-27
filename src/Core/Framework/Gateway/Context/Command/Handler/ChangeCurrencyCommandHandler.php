<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Gateway\Context\Command\Handler;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeCurrencyCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('framework')]
class ChangeCurrencyCommandHandler extends AbstractContextGatewayCommandHandler
{
    public function __construct(
        private readonly EntityRepository $currencyRepository
    ) {
    }

    /**
     * @param ChangeCurrencyCommand $command
     */
    public function handle(AbstractContextGatewayCommand $command, SalesChannelContext $context, array &$parameters): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('isoCode', $command->iso));

        $currencyId = $this->currencyRepository->searchIds($criteria, $context->getContext())->firstId();

        if ($currencyId === null) {
            return;
        }

        $parameters['currencyId'] = $currencyId;
    }

    public static function supportedCommands(): array
    {
        return [ChangeCurrencyCommand::class];
    }
}
