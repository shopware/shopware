<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Visitor;

use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Accumulates a violation for every element whose component is not a registered element type.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ComponentRegistrationVisitor implements ElementVisitor
{
    private readonly ConstraintViolationList $violations;

    public function __construct(
        private readonly AbstractContentSystemElementTypeRegistry $registry,
    ) {
        $this->violations = new ConstraintViolationList();
    }

    public function enter(ContentElement $element): void
    {
        $component = $element->getComponent();

        if ($this->registry->has($component)) {
            return;
        }

        $this->violations->add(new ConstraintViolation(
            \sprintf('Component "%s" is not a registered element type.', $component),
            null,
            [],
            null,
            $element->getId(),
            $component,
        ));
    }

    public function leave(ContentElement $element): void
    {
    }

    public function getViolations(): ConstraintViolationListInterface
    {
        return $this->violations;
    }
}
