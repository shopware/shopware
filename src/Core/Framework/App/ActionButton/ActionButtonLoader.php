<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ActionButton;

use Shopware\Core\Framework\App\Aggregate\ActionButton\ActionButtonCollection;
use Shopware\Core\Framework\App\Aggregate\ActionButton\ActionButtonEntity;
use Shopware\Core\Framework\App\Aggregate\ActionButtonTranslation\ActionButtonTranslationEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class ActionButtonLoader
{
    /**
     * @var EntityRepositoryInterface
     */
    private $actionButtonRepository;

    public function __construct(EntityRepositoryInterface $actionButtonRepository)
    {
        $this->actionButtonRepository = $actionButtonRepository;
    }

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

    private function formatCollection(ActionButtonCollection $actionButtons): array
    {
        return array_values(array_map(function (ActionButtonEntity $button): array {
            return [
                'app' => $button->getApp()->getName(),
                'id' => $button->getId(),
                'label' => $this->mapTranslatedLabels($button),
                'action' => $button->getAction(),
                'url' => $button->getUrl(),
                /*
                 * @feature-deprecated (FEATURE_NEXT_14360) tag:v6.5.0 - "openNewTab" key will be removed.
                 * It will no longer be used in the manifest.xml file
                 * and will be processed in the Executor with an OpenNewTabResponse response instead.
                 */
                'openNewTab' => $button->isOpenNewTab(),
                'icon' => $button->getApp()->getIcon(),
            ];
        }, $actionButtons->getElements()));
    }

    private function mapTranslatedLabels(ActionButtonEntity $button): array
    {
        $labels = [];

        /** @var ActionButtonTranslationEntity $translation */
        foreach ($button->getTranslations() as $translation) {
            $labels[$translation->getLanguage()->getLocale()->getCode()] = $translation->getLabel();
        }

        return $labels;
    }
}
