<?php declare(strict_types=1);

namespace Shopware\Administration\Framework\App\Subscriber;

use Shopware\Administration\Snippet\AppAdministrationSnippetCollection;
use Shopware\Administration\Snippet\AppAdministrationSnippetEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Maintenance\System\Service\ShopConfigurator;
use Shopware\Core\Maintenance\System\Service\SystemLanguageChangeEvent;
use Shopware\Core\System\Locale\LocaleCollection;
use Shopware\Core\System\Locale\LocaleException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class SystemLanguageChangedSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<LocaleCollection> $localeRepository
     * @param EntityRepository<AppAdministrationSnippetCollection> $snippetRepository
     */
    public function __construct(
        private EntityRepository $localeRepository,
        private EntityRepository $snippetRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SystemLanguageChangeEvent::class => 'onSystemLanguageChanged',
        ];
    }

    public function onSystemLanguageChanged(SystemLanguageChangeEvent $event): void
    {
        /**
         * If the system language is changed from "en-GB" to "de-DE", the languages are swapped, keeping the IDs.
         * Thus, snippets do not need to be updated in this case.
         *
         * @see ShopConfigurator::setDefaultLanguage()
         */
        if ($event->previousLocaleCode === 'en-GB' && $event->newLocaleCode === 'de-DE') {
            return;
        }

        $context = Context::createDefaultContext();

        $snippets = $this->getSnippets($context);
        if ($snippets->count() === 0) {
            return;
        }

        $appsWithSnippets = array_values(array_unique($snippets->map(static fn (AppAdministrationSnippetEntity $snippet) => $snippet->getAppId())));

        $previousLocaleId = $this->getLocaleId($event->previousLocaleCode, $context);
        $newLocaleId = $this->getLocaleId($event->newLocaleCode, $context);

        foreach ($appsWithSnippets as $appId) {
            $updates = [];

            // Reassign the snippet that was previously associated with the new locale to the previous locale
            $snippetForPreviousLocale = $this->snippetForLocale($snippets, $appId, $newLocaleId);
            if ($snippetForPreviousLocale) {
                $updates[] = [
                    'id' => $snippetForPreviousLocale->getId(),
                    'localeId' => $previousLocaleId,
                ];
            }

            // Update the snippet that was previously associated with the previous locale to be associated with the new locale
            $snippetForNewLocale = $this->snippetForLocale($snippets, $appId, $previousLocaleId);
            if ($snippetForNewLocale) {
                $updates[] = [
                    'id' => $snippetForNewLocale->getId(),
                    'localeId' => $newLocaleId,
                ];
            }

            $this->snippetRepository->update($updates, $context);
        }
    }

    private function getLocaleId(string $code, Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('code', $code));

        $localeId = $this->localeRepository->searchIds($criteria, $context)->firstId();
        if ($localeId === null) {
            throw LocaleException::localeDoesNotExists($code);
        }

        return $localeId;
    }

    private function getSnippets(Context $context): AppAdministrationSnippetCollection
    {
        return $this->snippetRepository->search(new Criteria(), $context)->getEntities();
    }

    private function snippetForLocale(
        AppAdministrationSnippetCollection $snippets,
        string $appId,
        string $localeId
    ): ?AppAdministrationSnippetEntity {
        return $snippets->filter(static function (AppAdministrationSnippetEntity $snippet) use ($appId, $localeId) {
            return $appId === $snippet->getAppId() && $snippet->getLocaleId() === $localeId;
        })->first();
    }
}
