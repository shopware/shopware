<?php declare(strict_types=1);

namespace Shopware\Elasticsearch;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
final readonly class TranslatedResolvedField extends ResolvedField
{
    public function __construct(
        Field $resolvedField,
        private TranslatedField $translatedField,
        ?string $root = null,
    ) {
        parent::__construct($resolvedField, $root);
    }

    public function getTranslatedField(): TranslatedField
    {
        return $this->translatedField;
    }
}
