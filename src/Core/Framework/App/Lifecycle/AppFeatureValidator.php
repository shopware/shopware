<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class AppFeatureValidator
{
    public function __construct(private readonly string $env)
    {
    }

    /**
     * Certain app features require an app secret to be set. In dev mode, throw if these features are
     * used without a secret so the developer gets immediate feedback.
     */
    public function validate(AppEntity $app, Manifest $manifest): void
    {
        if ($app->getAppSecret()) {
            return;
        }

        if ($this->env !== 'dev') {
            return;
        }

        $usedFeatures = [];

        if (($manifest->getAdmin()?->getModules() ?? []) !== []) {
            $usedFeatures[] = 'Admin Modules';
        }

        if (($manifest->getPayments()?->getPaymentMethods() ?? []) !== []) {
            $usedFeatures[] = 'Payment Methods';
        }

        if (($manifest->getTax()?->getTaxProviders() ?? []) !== []) {
            $usedFeatures[] = 'Tax providers';
        }

        if (($manifest->getWebhooks()?->getWebhooks() ?? []) !== []) {
            $usedFeatures[] = 'Webhooks';
        }

        if ($usedFeatures !== []) {
            throw AppException::appSecretRequiredForFeatures($app->getName(), $usedFeatures);
        }
    }
}
