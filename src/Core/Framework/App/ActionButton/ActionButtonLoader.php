<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ActionButton;

use Shopware\Core\Framework\App\Aggregate\ActionButton\ActionButtonCollection;
use Shopware\Core\Framework\App\Aggregate\ActionButton\ActionButtonEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @phpstan-type ActionButtonArray array{app: string, id: string, label: array<string, string|null>, action: string, url: string, icon: string}
 */
#[Package('core')]
class ActionButtonLoader
{
    /**
     * @param EntityRepository<ActionButtonCollection> $actionButtonRepository
     */
    public function __construct(private readonly EntityRepository $actionButtonRepository)
    {
    }

    /**
     * @return list<ActionButtonArray>
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

        $actionButtons = $this->actionButtonRepository->search($criteria, $context)->getEntities();

        return $this->formatCollection($actionButtons);
    }

    /**
     * @return list<ActionButtonArray>
     */
    private function formatCollection(ActionButtonCollection $actionButtons): array
    {
        return array_values($actionButtons->map(function (ActionButtonEntity $button): array {
            $app = $button->getApp();
            \assert($app !== null);

            return [
                'app' => $app->getName(),
                'id' => $button->getId(),
                'label' => $this->mapTranslatedLabels($button),
                'action' => $button->getAction(),
                'url' => $button->getUrl(),
                'icon' => $app->getIcon(),
            ];
        }));
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
            $language = $translation->getLanguage();
            if ($language === null) {
                continue;
            }

            $locale = $language->getLocale();
            if ($locale === null) {
                continue;
            }
            $labels[$locale->getCode()] = $translation->getLabel();
        }

        return $labels;
    }
}
