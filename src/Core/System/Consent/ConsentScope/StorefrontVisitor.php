<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\ConsentScope;

use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentException;
use Shopware\Core\System\Consent\ConsentScope;

/**
 * Scope for consents given by anonymous storefront visitors.
 *
 * Visitors are intentionally not identified (privacy by design), so the
 * identifier is always the literal `anonymous`. Consent evidence for this
 * scope is stored in dedicated log tables, not in the consent state storage.
 *
 * @internal
 */
#[Package('data-services')]
class StorefrontVisitor implements ConsentScope
{
    public const NAME = 'storefront_visitor';

    public const IDENTIFIER = 'anonymous';

    public function getName(): string
    {
        return self::NAME;
    }

    public function appliesTo(Context $context): bool
    {
        return $context->getSource() instanceof SalesChannelApiSource;
    }

    public function resolveIdentifier(Context $context): string
    {
        if (!$context->getSource() instanceof SalesChannelApiSource) {
            throw ConsentException::cannotResolveScope(self::NAME);
        }

        return self::IDENTIFIER;
    }

    public function resolveActorIdentifier(Context $context): string
    {
        return $this->resolveIdentifier($context);
    }
}
