<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Gateway\Context\Command\Handler;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeLanguageCommand;
use Shopware\Core\Framework\Gateway\GatewayException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @extends AbstractContextGatewayCommandHandler<ChangeLanguageCommand>
 *
 * @internal
 */
#[Package('framework')]
class ChangeLanguageCommandHandler extends AbstractContextGatewayCommandHandler
{
    /**
     * @internal
     *
     * @param EntityRepository<LanguageCollection> $languageRepository
     */
    public function __construct(
        private readonly EntityRepository $languageRepository,
    ) {
    }

    public function handle(AbstractContextGatewayCommand $command, SalesChannelContext $context, array &$parameters): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('locale.code', $command->iso));

        $languageId = $this->languageRepository->searchIds($criteria, $context->getContext())->firstId();

        if ($languageId === null) {
            throw GatewayException::handlerException('Language with iso code {{ isoCode }} not found', ['isoCode' => $command->iso]);
        }

        $parameters['languageId'] = $languageId;
    }

    public static function supportedCommands(): array
    {
        return [ChangeLanguageCommand::class];
    }
}
