<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Components;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

#[Package('framework')]
class UxComponentCollection extends Collection
{
    public function __construct(iterable $elements = [])
    {
        parent::__construct();

        foreach ($elements as $element) {
            $this->validateType($element);

            $this->set($element->getName(), $element);
        }
    }

    public function add($element): void
    {
        $this->validateType($element);
        $this->set($element->getName(), $element);
    }
}