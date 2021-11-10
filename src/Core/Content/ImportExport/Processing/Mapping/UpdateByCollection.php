<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Mapping;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method UpdateBy[]    getIterator()
 * @method UpdateBy[]    getElements()
 * @method UpdateBy|null get(string $key)
 * @method UpdateBy|null first()
 * @method UpdateBy|null last()
 */
class UpdateByCollection extends Collection
{
    /**
     * @param UpdateBy $element
     */
    public function add($element): void
    {
        $this->validateType($element);
        parent::set($element->getEntityName(), $element);
    }

    public function getExpectedClass(): ?string
    {
        return UpdateBy::class;
    }

    public static function fromIterable(iterable $data): self
    {
        if ($data instanceof UpdateByCollection) {
            return $data;
        }

        $updateByCollection = new self();

        foreach ($data as $updateBy) {
            if (\is_string($updateBy)) {
                $updateBy = new UpdateBy($updateBy);
            } elseif (\is_array($updateBy)) {
                $updateBy = UpdateBy::fromArray($updateBy);
            }

            if ($updateBy instanceof UpdateBy) {
                $updateByCollection->add($updateBy);
            }
        }

        return $updateByCollection;
    }
}
