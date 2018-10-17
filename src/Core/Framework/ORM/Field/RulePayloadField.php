<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Field;

use Shopware\Core\Framework\ORM\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\ORM\Write\EntityExistence;

class RulePayloadField extends JsonField
{
    private function buildRule(&$values)
    {
        if (array_key_exists( 'ruleType', $values)) {
            $values['_class'] = $values['ruleType'];
        }
        foreach ($values as $key => &$value) {
            if (!is_array($value)) {
                continue;
            }

            $this->buildRule($value);
        }
    }

    protected function invoke(EntityExistence $existence, KeyValuePair $data): \Generator
    {
        $value = $data->getValue();
        if (!empty($data->getValue())) {
            $this->buildRule($value);
        }

        if ($existence->exists()) {
            $this->validate($this->getUpdateConstraints(), $data->getKey(), $value);
        } else {
            $this->validate($this->getInsertConstraints(), $data->getKey(), $value);
        }

        if (!\is_string($value) && $value !== null) {
            $value = json_encode($value);
        }

        yield $this->storageName => $value;
    }
}