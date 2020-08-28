<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\System\Language\LanguageEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CustomFieldService implements EventSubscriberInterface
{
    /**
     * @var string[]|null
     */
    private $customFields;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepositoryInterface
     */
    private $customFieldRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $languageRepository;

    public function __construct(
        Connection $connection,
        EntityRepositoryInterface $customFieldRepository,
        EntityRepositoryInterface $languageRepository
    ) {
        $this->connection = $connection;
        $this->customFieldRepository = $customFieldRepository;
        $this->languageRepository = $languageRepository;
    }

    public function getCustomField(string $attributeName): ?Field
    {
        $type = $this->getCustomFields()[$attributeName] ?? null;
        if (!$type) {
            return null;
        }

        switch ($type) {
            case CustomFieldTypes::INT:
                return new IntField($attributeName, $attributeName);

            case CustomFieldTypes::FLOAT:
                return new FloatField($attributeName, $attributeName);

            case CustomFieldTypes::BOOL:
                return new BoolField($attributeName, $attributeName);

            case CustomFieldTypes::DATETIME:
                return new DateTimeField($attributeName, $attributeName);

            case CustomFieldTypes::TEXT:
                return new LongTextField($attributeName, $attributeName);

            case CustomFieldTypes::HTML:
                return (new LongTextField($attributeName, $attributeName))->addFlags(new AllowHtml());

            case CustomFieldTypes::JSON:
            default:
                return new JsonField($attributeName, $attributeName);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomFieldEvents::CUSTOM_FIELD_DELETED_EVENT => 'invalidateCache',
            CustomFieldEvents::CUSTOM_FIELD_WRITTEN_EVENT => 'invalidateCache',
        ];
    }

    /**
     * @internal
     */
    public function invalidateCache(): void
    {
        $this->customFields = null;
    }

    public function getCustomFieldLabels(array $customFieldNames, Context $context): array
    {
        if ($customFieldNames === []) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('name', $customFieldNames));

        /** @var CustomFieldEntity[] $customFields */
        $customFields = $this->customFieldRepository->search($criteria, $context)->getElements();

        if ($customFields === []) {
            return [];
        }

        $criteria = new Criteria($context->getLanguageIdChain());
        $criteria->addAssociation('locale');
        $languageEntityChain = $this->languageRepository->search($criteria, $context)->getElements();

        $labels = [];
        foreach ($customFields as $customField) {
            foreach ($context->getLanguageIdChain() as $languageId) {
                if (!isset($languageEntityChain[$languageId])) {
                    continue;
                }

                /** @var LanguageEntity $language */
                $language = $languageEntityChain[$languageId];
                if ($language->getLocale() === null) {
                    continue;
                }

                $locale = $language->getLocale()->getCode();
                if (isset($customField->getConfig()['label'][$locale])) {
                    $labels[$customField->getName()] = $customField->getConfig()['label'][$locale];

                    break;
                }
            }
        }

        return $labels;
    }

    /**
     * @return string[]
     */
    private function getCustomFields(): array
    {
        if ($this->customFields !== null) {
            return $this->customFields;
        }

        $fields = $this->connection->fetchAll('SELECT `name`, `type` FROM `custom_field` WHERE `active` = 1');

        $this->customFields = FetchModeHelper::keyPair($fields);

        return $this->customFields;
    }
}
