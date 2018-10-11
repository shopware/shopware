<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Field;

use Shopware\Core\Framework\ORM\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\ORM\Write\EntityExistence;
use Shopware\Core\Framework\ORM\Write\FieldException\MalformatDataException;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\System\Exception\InvalidLocaleCodeException;

class TranslationsAssociationField extends Field implements AssociationInterface
{
    use AssociationTrait;

    /**
     * @var string
     */
    protected $localField;

    /**
     * @var string
     */
    protected $referenceField;

    public function __construct(
        string $referenceClass,
        string $propertyName = 'translations',
        bool $loadInBasic = false,
        string $localField = 'id'
    ) {
        parent::__construct($propertyName);
        $this->loadInBasic = $loadInBasic;
        $this->localField = $localField;
        $this->referenceField = 'languageId';
        $this->referenceClass = $referenceClass;
    }

    public function getReferenceField(): string
    {
        return $this->referenceField;
    }

    public function getLocalField(): string
    {
        return $this->localField;
    }

    public function getExtractPriority(): int
    {
        return 90;
    }

    /**
     * @throws InvalidLocaleCodeException
     */
    protected function invoke(EntityExistence $existence, KeyValuePair $data): \Generator
    {
        $value = $data->getValue();
        if ($value === null) {
            $value = [
                $this->writeContext->getContext()->getLanguageId() => [],
            ];
            $data = new KeyValuePair($data->getKey(), $value, $data->isRaw());

            return $this->map($data);
        }

        foreach ($value as $identifier => $fields) {
            /* multiple formats are supported.
                translations => [['property' => 'translation', 'languageId' => '{languageUuid}']] -> skip
                translations => ['{languageUuid}' => ['property' => 'translation']] -> skip
                translations => ['en_GB' => ['property' => 'translation']] -> proceed and use localeLanguageResolver
            */
            if (is_numeric($identifier) || Uuid::isValid($identifier)) {
                continue;
            }

            $languageId = $this->localeLanguageResolver->getLanguageByLocale($identifier, $this->writeContext->getContext());

            if (!isset($value[$languageId])) {
                $value[$languageId] = $fields;
            } else {
                $value[$languageId] = array_merge($value[$identifier], $value[$languageId]);
            }

            unset($value[$identifier]);
        }
        $data = new KeyValuePair($data->getKey(), $value, $data->isRaw());

        return $this->map($data);
    }

    protected function map(KeyValuePair $data): \Generator
    {
        $key = $data->getKey();
        $value = $data->getValue();

        if (!\is_array($value)) {
            throw new MalformatDataException($this->path . '/' . $key, 'Value must be an array.');
        }

        $isNumeric = array_keys($value) === range(0, \count($value) - 1);

        foreach ($value as $keyValue => $subresources) {
            if (!\is_array($subresources)) {
                throw new MalformatDataException($this->path . '/' . $key, 'Value must be an array.');
            }

            if ($this->referenceField && !$isNumeric) {
                $subresources[$this->referenceField] = $keyValue;
            }

            $this->writeResource->extract(
                $subresources,
                $this->referenceClass,
                $this->exceptionStack,
                $this->commandQueue,
                $this->writeContext,
                $this->fieldExtenderCollection,
                $this->path . '/' . $key . '/' . $keyValue
            );
        }

        return;
        yield __CLASS__ => __METHOD__;
    }
}
