<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Start with original raw result
 *  -> each step removes one from the raw set and possibly adds one to the result set
 * if raw set is empty, reiterate the result set
 *  -> if skipped => KEEP
 *  -> if used
 *      -> if not array and a different key comes back -> REMOVE
 *      -> if array with same key -> UPDATE RECURSIVELY
 *
 *      foreach($keys as KEY) {
 *
 *          if(!$stack->has('KEY')) {
 *              skip;
 *          }
 *
 *          $kvPair = $stack->pop('KEY');
 *
 *          foreach($provider($kvPair) as $key => $value) {
 *              $stack->update($key, $value); // determine state
 *          }
 *
 *      }
 *
 *      $resultSet = $stack->getResultAsArray();
 */
#[Package('core')]
class DataStack
{
    /**
     * @var KeyValuePair[]
     */
    private array $data = [];

    /**
     * @param array<string, mixed> $originalData
     */
    public function __construct(array $originalData)
    {
        if (\array_key_exists('extensions', $originalData)) {
            $originalData = array_merge($originalData, $originalData['extensions']);
            unset($originalData['extensions']);
        }

        foreach ($originalData as $key => $value) {
            $this->data[$key] = new KeyValuePair($key, $value, true);
        }
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function pop(string $key): ?KeyValuePair
    {
        if (!$this->has($key)) {
            return null;
        }

        $pair = $this->data[$key];
        unset($this->data[$key]);

        return $pair;
    }

    public function update(string $key, mixed $value): void
    {
        if (!$this->has($key)) {
            $this->data[$key] = new KeyValuePair($key, $value, false);

            return;
        }

        $preExistingPair = $this->data[$key];

        if (!\is_array($value) || !\is_array($preExistingPair->getValue())) {
            $this->data[$key] = new KeyValuePair($key, $value, false);

            return;
        }

        $this->data[$key] = new KeyValuePair(
            $key,
            array_replace_recursive($preExistingPair->getValue(), $value),
            false
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getResultAsArray(): array
    {
        $resultPairs = [];
        foreach ($this->data as $kvPair) {
            if (!$kvPair->isRaw()) {
                $resultPairs[$kvPair->getKey()] = $kvPair->getValue();
            }
        }

        return $resultPairs;
    }

    public function add(string $key, mixed $value, bool $isDefault): void
    {
        $this->data[$key] = new KeyValuePair($key, $value, true, $isDefault);
    }
}
