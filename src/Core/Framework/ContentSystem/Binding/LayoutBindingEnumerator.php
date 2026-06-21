<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * Enumerates the distinct source bindings of a content layout, so the well-formedness subscriber can
 * re-check an already-bound layout against each source it is bound to (section-agnostic via the tag).
 * Core registers the entity-assignment enumerator; Storefront registers the header/footer enumerator.
 */
#[Package('framework')]
interface LayoutBindingEnumerator
{
    /**
     * @return list<SourceBinding> distinct source bindings for this layout, each with its provided root-context set
     */
    public function enumerate(string $contentLayoutId, Context $context): array;
}
