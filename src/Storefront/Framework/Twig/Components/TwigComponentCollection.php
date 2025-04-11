<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Components;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<TwigComponent>
 */
#[Package('framework')]
class TwigComponentCollection extends Collection
{
    public function __construct(iterable $elements = [])
    {
        parent::__construct();

        foreach ($elements as $element) {
            $this->validateType($element);

            $this->set($element->getTag(), $element);
        }
    }

    public function add($element): void
    {
        $this->validateType($element);
        $this->set($element->getTag(), $element);
    }

    protected function getExpectedClass(): string
    {
        return TwigComponent::class;
    }
}
