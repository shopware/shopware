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

    public function getScopeIdentifier(Context $context): string
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

    public function getActorIdentifier(Context $context): ?string
    {
        return null;
    }
}
