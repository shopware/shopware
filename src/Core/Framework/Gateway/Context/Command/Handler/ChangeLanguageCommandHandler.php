<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Gateway\Context\Command\Handler;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeLanguageCommand;
use Shopware\Core\Framework\Gateway\Context\ContextGatewayException;
use Shopware\Core\Framework\Log\ExceptionLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('framework')]
class ChangeLanguageCommandHandler extends AbstractContextGatewayCommandHandler
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $languageRepository,
    ) {
    }

    /**
     * @param ChangeLanguageCommand $command
     */
    public function handle(AbstractContextGatewayCommand $command, SalesChannelContext $context, array &$parameters): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('locale.code', $command->iso));

        $languageId = $this->languageRepository->searchIds($criteria, $context->getContext())->firstId();

        if ($languageId === null) {
            throw ContextGatewayException::handlerException('Language with iso code {{ isoCode }} not found', ['isoCode' => $command->iso]);
        }

        $parameters['languageId'] = $languageId;
    }

    public static function supportedCommands(): array
    {
        return [ChangeLanguageCommand::class];
    }
}
