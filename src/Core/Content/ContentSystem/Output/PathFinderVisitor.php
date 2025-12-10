<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Output;

use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Element\Visitor\ElementVisitor;
use Shopware\Core\Framework\Log\Package;

/**
 * Finds path from root to target using enter/leave pattern.
 *
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class PathFinderVisitor implements ElementVisitor
{
    /**
     * @var list<string>
     */
    private array $currentPath = [];

    /**
     * @var list<string>
     */
    private array $foundPath = [];

    public function __construct(
        private readonly string $targetId
    ) {
    }

    public function enter(ContentElement $element): void
    {
        $this->currentPath[] = $element->getId();

        // Capture path when target found (only first occurrence)
        if ($this->foundPath === [] && $element->getId() === $this->targetId) {
            $this->foundPath = $this->currentPath;
        }
    }

    public function leave(ContentElement $element): void
    {
        // Automatic backtracking as we exit each node
        array_pop($this->currentPath);
    }

    /**
     * @return list<string> Element IDs from root to target (inclusive), empty if not found
     */
    public function getPath(): array
    {
        return $this->foundPath;
    }
}
