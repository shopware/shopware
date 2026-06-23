<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Validation;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ContentElementListField;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ContentElementListFieldSerializer;
use Shopware\Core\Framework\Log\Package;

/**
 * Single decode path from a content_layout `layout` storage value to its element tree, shared by the
 * well-formedness validator and the binding checker so the decode step cannot drift between them. Throws on any
 * decode defect; each caller keeps its own catch policy ({@see ContentLayoutWriteValidator} records an
 * invalid_config violation, {@see LayoutBindingChecker} skips to the committed-store fallback).
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class LayoutTreeDecoder
{
    public function __construct(
        private readonly ContentLayoutDefinition $definition,
        private readonly ContentElementListFieldSerializer $listSerializer,
    ) {
    }

    /**
     * @throws ContentSystemException
     *
     * @return list<ContentElement>
     */
    public function decode(mixed $value): array
    {
        $field = $this->definition->getField(ContentLayoutDefinition::LAYOUT_FIELD);
        \assert($field instanceof ContentElementListField);

        return array_values($this->listSerializer->decode($field, $value) ?? []);
    }
}
