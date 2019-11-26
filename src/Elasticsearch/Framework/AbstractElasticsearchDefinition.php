<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\NumberRange\DataAbstractionLayer\NumberRangeField;
use Shopware\Elasticsearch\Framework\Indexing\EntityMapper;

abstract class AbstractElasticsearchDefinition
{
    /**
     * @var EntityMapper
     */
    protected $mapper;

    public function __construct(EntityMapper $mapper)
    {
        $this->mapper = $mapper;
    }

    abstract public function getEntityDefinition(): EntityDefinition;

    public function getMapping(Context $context): array
    {
        $definition = $this->getEntityDefinition();

        return [
            '_source' => ['includes' => ['id', 'fullText', 'fullTextBoosted']],
            'properties' => $this->mapper->mapFields($definition, $context),
        ];
    }

    public function extendCriteria(Criteria $criteria): void
    {
    }

    public function buildFullText(Entity $entity): FullText
    {
        $fullText = [];
        $boosted = [];

        foreach ($this->getEntityDefinition()->getFields() as $field) {
            $real = $field;

            $isTranslated = $field instanceof TranslatedField;

            if ($isTranslated) {
                $real = EntityDefinitionQueryHelper::getTranslatedField($this->getEntityDefinition(), $field);
            }

            if (!$real instanceof StringField) {
                continue;
            }

            try {
                if ($isTranslated) {
                    $value = $entity->getTranslation($real->getPropertyName());
                } else {
                    $value = $entity->get($real->getPropertyName());
                }
            } catch (\Exception $e) {
                continue;
            }

            $fullText[] = $value;

            if ($isTranslated || $field instanceof NumberRangeField) {
                $boosted[] = $value;
            }
        }

        $fullText = array_filter($fullText);
        $boosted = array_filter($boosted);

        return new FullText(implode(' ', $fullText), implode(' ', $boosted));
    }
}
