<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ActionButton;

use Shopware\Core\Framework\App\Aggregate\ActionButton\ActionButtonCollection;
use Shopware\Core\Framework\App\Aggregate\ActionButton\ActionButtonEntity;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[Package('core')]
class ActionButtonLoader
{
    public function __construct(private readonly EntityRepository $actionButtonRepository)
    {
    }

    /**
     * @return array<int, array<string, array<string, string|null>|string|null>>
     */
    public function loadActionButtonsForView(string $entity, string $view, Context $context): array
    {
        $criteria = new Criteria();
        $criteria
            ->addAssociation('app')
            ->addAssociation('translations.language.locale')
            ->addFilter(
                new EqualsFilter('entity', $entity),
                new EqualsFilter('view', $view),
                new EqualsFilter('app.active', true)
            );

        /** @var ActionButtonCollection $actionButtons */
        $actionButtons = $this->actionButtonRepository->search($criteria, $context)->getEntities();

        return $this->formatCollection($actionButtons);
    }

    /**
     * @return array<int, array<string, array<string, string|null>|string|null>>
     */
    private function formatCollection(ActionButtonCollection $actionButtons): array
    {
        return array_values(array_map(function (ActionButtonEntity $button): array {
            /** @var AppEntity $app */
            $app = $button->getApp();

            return [
                'app' => $app->getName(),
                'id' => $button->getId(),
                'label' => $this->mapTranslatedLabels($button),
                'action' => $button->getAction(),
                'url' => $button->getUrl(),
                'icon' => $app->getIcon(),
            ];
        }, $actionButtons->getElements()));
    }

    /**
     * @return array<string, string|null>
     */
    private function mapTranslatedLabels(ActionButtonEntity $button): array
    {
        $translations = $button->getTranslations();

        if ($translations === null) {
            return [];
        }

        $labels = [];
        foreach ($translations as $translation) {
            /** @var LanguageEntity $language */
            $language = $translation->getLanguage();

            /** @var LocaleEntity $locale */
            $locale = $language->getLocale();
            $labels[$locale->getCode()] = $translation->getLabel();
        }

        return $labels;
    }
}
