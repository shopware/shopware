<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\Error\ErrorCollection;
use Shopware\Core\Framework\App\Validation\Error\MissingPermissionError;
use Shopware\Core\Framework\App\Validation\Error\NotHookableError;
use Shopware\Core\Framework\App\Validation\Error\WebhookNotPermittedError;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\Subscriber;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\SubscriptionValidator;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class HookableValidator extends AbstractManifestValidator
{
    public function __construct(private readonly SubscriptionValidator $subscriptionValidator)
    {
    }

    public function validate(Manifest $manifest, Context $context): ErrorCollection
    {
        $errors = new ErrorCollection();
        $webhooks = $manifest->getWebhooks();
        $webhooks = $webhooks ? $webhooks->getWebhooks() : [];

        if (!$webhooks) {
            return $errors;
        }

        $appPrivileges = $manifest->getPermissions();
        $appPrivileges = $appPrivileges ? $appPrivileges->asParsedPrivileges() : [];

        $subscriptions = [];
        foreach ($webhooks as $webhook) {
            $subscriptions[$webhook->getName() . ': ' . $webhook->getEvent()] = $webhook->getEvent();
        }

        $refusals = $this->subscriptionValidator->validate($subscriptions, $appPrivileges, Subscriber::app($manifest), $context);

        if ($refusals->notHookable !== []) {
            $errors->add(new NotHookableError($refusals->notHookable));
        }

        if ($refusals->notPermitted !== []) {
            $errors->add(new WebhookNotPermittedError($refusals->notPermitted));
        }

        if ($refusals->allMissingPrivileges !== []) {
            $errors->add(new MissingPermissionError($refusals->allMissingPrivileges));
        }

        return $errors;
    }
}
