<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\TranslationFieldResolver;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\TranslatedFieldSerializer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageDefinition;

#[Package('core')]
class TranslatedField extends Field
{
    final public const PRIORITY = 100;

    private readonly string $foreignClassName;

    private readonly string $foreignFieldName;

    public function __construct(string $propertyName)
    {
        $this->foreignClassName = LanguageDefinition::class;
        $this->foreignFieldName = 'id';

        parent::__construct($propertyName);
    }

    public function getExtractPriority(): int
    {
        return self::PRIORITY;
    }

    public function getForeignClassName(): string
    {
        return $this->foreignClassName;
    }

    public function getForeignFieldName(): string
    {
        return $this->foreignFieldName;
    }

    protected function getSerializerClass(): string
    {
        return TranslatedFieldSerializer::class;
    }

    protected function getResolverClass(): ?string
    {
        return TranslationFieldResolver::class;
    }
}
