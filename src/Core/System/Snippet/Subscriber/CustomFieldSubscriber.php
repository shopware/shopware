<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Subscriber;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\CustomField\CustomFieldEntity;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetEntity;
use Shopware\Core\System\Snippet\SnippetEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CustomFieldSubscriber implements EventSubscriberInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $snippetRepo;

    /**
     * @var EntityRepositoryInterface
     */
    private $snippetSetRepo;

    /**
     * @var EntityRepositoryInterface
     */
    private $customFieldRepo;

    public function __construct(EntityRepositoryInterface $snippetRepo, EntityRepositoryInterface $snippetSetRepo, EntityRepositoryInterface $customFieldRepo)
    {
        $this->snippetRepo = $snippetRepo;
        $this->snippetSetRepo = $snippetSetRepo;
        $this->customFieldRepo = $customFieldRepo;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'custom_field.written' => 'customFieldIsWritten',
        ];
    }

    public function customFieldIsWritten(EntityWrittenEvent $event): void
    {
        $context = $event->getContext();
        /** @var SnippetSetEntity[] $snippetSets */
        $snippetSets = $this->snippetSetRepo->search(new Criteria(), $context)->getElements();

        if (empty($snippetSets)) {
            return;
        }

        $snippets = [];
        foreach ($event->getWriteResults() as $writeResult) {
            if (!isset($writeResult->getPayload()['config']['label']) || empty($writeResult->getPayload()['config']['label'])) {
                continue;
            }

            if ($writeResult->getOperation() === EntityWriteResult::OPERATION_UPDATE) {
                $this->setUpdateSnippets($writeResult, $context, $snippets);
            }

            if ($writeResult->getOperation() === EntityWriteResult::OPERATION_INSERT) {
                $this->setInsertSnippets($writeResult, $snippetSets, $snippets);
            }
        }

        if (empty($snippets)) {
            return;
        }

        $this->snippetRepo->upsert($snippets, $context);
    }

    private function setUpdateSnippets(EntityWriteResult $writeResult, Context $context, array &$snippets): void
    {
        $id = $writeResult->getPrimaryKey();

        if (!\is_string($id)) {
            return;
        }

        /** @var CustomFieldEntity $customFieldEntity */
        $customFieldEntity = $this->customFieldRepo->search(new Criteria([$id]), $context)->first();
        $labels = $writeResult->getPayload()['config']['label'];
        $locales = array_keys($writeResult->getPayload()['config']['label']);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translationKey', 'customFields.' . $customFieldEntity->getName()));
        $criteria->addFilter(new EqualsAnyFilter('set.iso', $locales));
        $criteria->addAssociation('set');
        /** @var SnippetEntity[] $updateSnippets */
        $updateSnippets = $this->snippetRepo->search($criteria, $context)->getElements();

        foreach ($updateSnippets as $snippet) {
            if (!isset($labels[$snippet->getSet()->getIso()]) || $labels[$snippet->getSet()->getIso()] === $snippet->getValue()) {
                continue;
            }

            $snippets[] = [
                'id' => $snippet->getId(),
                'value' => $labels[$snippet->getSet()->getIso()],
            ];
        }
    }

    /**
     * @param SnippetSetEntity[] $snippetSets
     */
    private function setInsertSnippets(EntityWriteResult $writeResult, array $snippetSets, array &$snippets): void
    {
        $name = $writeResult->getPayload()['name'];
        $labels = $writeResult->getPayload()['config']['label'];

        foreach ($snippetSets as $snippetSet) {
            $label = $name;
            $iso = $snippetSet->getIso();

            if (isset($labels[$iso])) {
                $label = $labels[$iso];
            }

            $snippets[] = [
                'setId' => $snippetSet->getId(),
                'translationKey' => 'customFields.' . $name,
                'value' => $label,
                'author' => 'System',
            ];
        }
    }
}
