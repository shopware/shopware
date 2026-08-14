<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\Error\Error;
use Shopware\Core\Framework\App\Validation\Error\MissingPermissionError;
use Shopware\Core\Framework\App\Validation\Error\NotHookableError;
use Shopware\Core\Framework\App\Validation\Error\RestrictedEventError;
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
        $permitted = $this->hookableEventCollector->getHookableEventNamesWithPrivileges($context, $manifest);
        $restricted = $this->hookableEventCollector->getRestrictedEventNames($manifest);

        $notPermitted = [];
        $notHookable = [];
        $missingPermissions = [];
        foreach ($webhooks as $webhook) {
            // A restricted event is always absent from the permitted set, so it has to be recognised
            // first or it reads as an event that does not exist.
            if (\in_array($webhook->getEvent(), $restricted, true)) {
                $notPermitted[] = $webhook->getName() . ': ' . $webhook->getEvent();

                continue;
            }

            if (!isset($permitted[$webhook->getEvent()])) {
                $notHookable[] = $webhook->getName() . ': ' . $webhook->getEvent();

                continue;
            }

            foreach ($permitted[$webhook->getEvent()]['privileges'] as $privilege) {
                if (\in_array($privilege, $appPrivileges, true)) {
                    continue;
                }

                $missingPermissions[] = $privilege;
            }
        }

        $errors = [];

        if ($notPermitted !== []) {
            $errors[] = new RestrictedEventError($notPermitted);
        }

        if ($notHookable !== []) {
            $errors[] = new NotHookableError($notHookable);
        }

        if ($missingPermissions !== []) {
            $errors[] = new MissingPermissionError($missingPermissions);
        }

        return $errors;
    }
}
