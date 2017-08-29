<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\Field;

use Shopware\Framework\Api2\FieldAware\WriteContextAware;
use Shopware\Framework\Api2\WriteContext;

class TranslatedField extends Field implements WriteContextAware
{

    /**
     * @var string
     */
    private $storageName;
    /**
     * @var string
     */
    private $referencedClassName;

    /**
     * @var WriteContext
     */
    private $writeContext;
    /**
     * @var string
     */
    private $foreignClassName;
    /**
     * @var string
     */
    private $foreignFieldName;

    /**
     * @param string $storageName
     */
    public function __construct(string $storageName, string $foreignClassName, string $foreignFieldName)
    {
        $this->foreignClassName = $foreignClassName;
        $this->foreignFieldName = $foreignFieldName;
        $this->storageName = $storageName;
    }

    /**
     * @return string
     */
    public function getStorageName(): string
    {
        return $this->storageName;
    }

    /**
     * @return string
     */
    public function getReferencedClassName(): string
    {
        return $this->referencedClassName;
    }


    public function setWriteContext(WriteContext $writeContext): void
    {
        $this->writeContext = $writeContext;
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(string $type, string $key, $value = null): \Generator
    {
        if (is_string($value)) {
            // load from write context the default language

            yield 'translations' => [
                $this->writeContext->get($this->foreignClassName, $this->foreignFieldName) => [
                    $key => $value,
                ]
            ];
            return;
        }

        if (is_array($value)) {
            $isNumeric = count(array_diff($value, range(0, count($value)))) === 0;

            if ($isNumeric) {
                foreach ($value as $translationKey => $translationValue) {
                    yield 'translations' => [
                        $translationKey => [
                            $key => $translationValue,
                        ]
                    ];
                }
            } else {
                foreach ($value as $translationKey => $translationValue) {
                    yield 'translations' => [
                        $translationKey => [
                            $key => $translationValue,
                        ]
                    ];
                }
            }
            return;
        }
    }
}