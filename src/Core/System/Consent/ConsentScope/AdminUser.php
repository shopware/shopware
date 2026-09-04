<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\ConsentScope;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentException;
use Shopware\Core\System\Consent\ConsentScope;

/**
 * @internal
 */
#[Package('data-services')]
class AdminUser implements ConsentScope
{
    public const NAME = 'admin_user';

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * Integrations also authenticate as AdminApiSource but carry no user id,
     * a per-user consent cannot apply to them.
     */
    public function appliesTo(Context $context): bool
    {
        $source = $context->getSource();

        return $source instanceof AdminApiSource && $source->getUserId();
    }

    public function resolveIdentifier(Context $context): string
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            throw ConsentException::cannotResolveScope(self::NAME);
        }

        $userId = $source->getUserId();
        if (!$userId) {
            throw ConsentException::cannotResolveScope(self::NAME);
        }

        return $userId;
    }

    public function resolveActorIdentifier(Context $context): string
    {
        return $this->resolveIdentifier($context);
    }
}
