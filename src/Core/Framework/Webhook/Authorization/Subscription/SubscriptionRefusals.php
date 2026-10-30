<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Authorization\Subscription;

use Shopware\Core\Framework\Log\Package;

/**
 * The refused subscriptions by reason, under the keys passed to SubscriptionValidator::validate().
 *
 * @internal only for use by the app-system
 */
#[Package('framework')]
final readonly class SubscriptionRefusals
{
    /**
     * @var list<string> every missing privilege, once
     */
    public array $allMissingPrivileges;

    /**
     * @param list<string> $notHookable
     * @param list<string> $notPermitted refused by a policy
     * @param array<string, list<string>> $missingPrivileges
     */
    public function __construct(
        public array $notHookable = [],
        public array $notPermitted = [],
        public array $missingPrivileges = [],
    ) {
        $this->allMissingPrivileges = array_values(array_unique(array_merge(...array_values($missingPrivileges))));
    }
}
