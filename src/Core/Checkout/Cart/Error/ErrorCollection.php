<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Error;

use Shopware\Core\Framework\Struct\Collection;

class ErrorCollection extends Collection
{
    /**
     * @var Error[]
     */
    protected $elements = [];

    public function add(Error $error): void
    {
        $this->elements[$error->getIdentifier()] = $error;
    }

    public function blockOrder(): bool
    {
        $states = $this->map(function (Error $error) {
            return $error->blockOrder();
        });

        return max($states);
    }

    public function hasLevel(int $errorLevel): bool
    {
        foreach ($this->elements as $element) {
            if ($element->getLevel() === $errorLevel) {
                return true;
            }
        }

        return false;
    }
}
