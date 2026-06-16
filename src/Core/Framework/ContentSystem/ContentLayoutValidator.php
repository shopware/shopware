<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Visitor\ComponentRegistrationVisitor;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Lean sanity validation for a decoded content layout. Walks the full element tree and collects
 * a violation for every component that is not a registered element type. Returns the violation
 * list rather than throwing, so it can be extended with renderability checks later.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ContentLayoutValidator
{
    public function __construct(
        private readonly AbstractContentSystemElementTypeRegistry $registry,
    ) {
    }

    /**
     * @param list<ContentElement> $elements
     */
    public function validate(array $elements): ConstraintViolationListInterface
    {
        $visitor = new ComponentRegistrationVisitor($this->registry);

        foreach ($elements as $element) {
            $element->traverse($visitor);
        }

        return $visitor->getViolations();
    }
}
