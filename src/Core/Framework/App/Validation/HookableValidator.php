<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\Error\Error;
use Shopware\Core\Framework\App\Validation\Error\MissingPermissionError;
use Shopware\Core\Framework\App\Validation\Error\NotHookableError;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventCollector;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class HookableValidator extends AbstractManifestValidator
{
    public function __construct(
        private readonly HookableEventCollector $hookableEventCollector,
    ) {
    }

    /**
     * @return list<Error>
     */
    public function validate(Manifest $manifest, Context $context): array
    {
        $webhooks = $manifest->getWebhooks();
        $webhooks = $webhooks ? $webhooks->getWebhooks() : [];

        if (!$webhooks) {
            return [];
        }

        $appPrivileges = $manifest->getPermissions();
        $appPrivileges = $appPrivileges ? $appPrivileges->asParsedPrivileges() : [];
        $hookableEventNamesWithPrivileges = $this->hookableEventCollector->getHookableEventNamesWithPrivileges($context, $manifest);
        $hookableEventNames = array_keys($hookableEventNamesWithPrivileges);

        $notHookable = [];
        $missingPermissions = [];
        foreach ($webhooks as $webhook) {
            // validate supported webhooks
            if (!\in_array($webhook->getEvent(), $hookableEventNames, true)) {
                $notHookable[] = $webhook->getName() . ': ' . $webhook->getEvent();

                continue;
            }

            // validate permissions
            foreach ($hookableEventNamesWithPrivileges[$webhook->getEvent()]['privileges'] as $privilege) {
                if (\in_array($privilege, $appPrivileges, true)) {
                    continue;
                }

                $missingPermissions[] = $privilege;
            }
        }

        $errors = [];

        if ($notHookable !== []) {
            $errors[] = new NotHookableError($notHookable);
        }

        if ($missingPermissions !== []) {
            $errors[] = new MissingPermissionError($missingPermissions);
        }

        return $errors;
    }
}
