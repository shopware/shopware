<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Snippet\Service\TranslationUpdater;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('discovery')]
#[AsMessageHandler(handles: UpdateTranslationsTask::class)]
final class UpdateTranslationsTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     * @param EntityRepository<LanguageCollection> $languageRepository
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly TranslationUpdater $translationUpdater,
        private readonly EntityRepository $languageRepository,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    /**
     * Refreshes the community translations of every language whose `translationAutoUpdate` field is enabled
     * (enabled by default). Only linked languages are considered: a flagged language whose translation is not
     * installed, or whose locale is not part of the translation set, is ignored and never triggers a request.
     */
    public function run(): void
    {
        $context = Context::createCLIContext();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translationAutoUpdate', true));
        $criteria->addAssociation('locale');

        $languages = $this->languageRepository->search($criteria, $context)->getEntities();

        $locales = array_values(array_unique(array_filter(
            $languages->map(static fn (LanguageEntity $language): ?string => $language->getLocale()?->getCode())
        )));

        if ($locales === []) {
            return;
        }

        $this->translationUpdater->updateInstalled($context, $locales);
    }
}
