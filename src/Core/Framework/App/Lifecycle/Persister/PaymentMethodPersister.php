<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use League\MimeTypeDetection\FinfoMimeTypeDetector;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodEntity;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleContext;
use Shopware\Core\Framework\App\Manifest\Xml\PaymentMethod\PaymentMethod;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class PaymentMethodPersister implements PersisterInterface
{
    private readonly FinfoMimeTypeDetector $mimeDetector;

    /**
     * @param EntityRepository<PaymentMethodCollection> $paymentMethodRepository
     */
    public function __construct(
        private readonly EntityRepository $paymentMethodRepository,
        private readonly MediaService $mediaService,
    ) {
        $this->mimeDetector = new FinfoMimeTypeDetector();
    }

    public function persist(AppLifecycleContext $context): void
    {
        if (!$context->hasAppSecret()) {
            return;
        }

        $manifest = $context->manifest;
        $appId = $context->app->getId();
        $appName = $manifest->getMetadata()->getName();

        $existingPaymentMethods = $this->getExistingPaymentMethods($appName, $appId, $context->context);

        $payments = $manifest->getPayments();
        $paymentMethods = $payments !== null ? $payments->getPaymentMethods() : [];
        $upserts = [];

        foreach ($paymentMethods as $paymentMethod) {
            $payload = $paymentMethod->toArray($context->defaultLocale);
            $payload['handlerIdentifier'] = \sprintf('app\\%s_%s', $appName, $paymentMethod->getIdentifier());
            $payload['technicalName'] = \sprintf('payment_%s_%s', $appName, $paymentMethod->getIdentifier());

            $existing = $existingPaymentMethods->filterByProperty('handlerIdentifier', $payload['handlerIdentifier'])->first();
            $existingAppPaymentMethod = $existing ? $existing->getAppPaymentMethod() : null;

            $payload['appPaymentMethod']['appId'] = $appId;
            $payload['appPaymentMethod']['appName'] = $appName;
            $payload['appPaymentMethod']['originalMediaId'] = $this->getMediaId($context->appFilesystem, $appName, $paymentMethod, $context->context, $existingAppPaymentMethod);

            if ($existing && $existingAppPaymentMethod) {
                $existingPaymentMethods->remove($existing->getId());

                $payload['id'] = $existing->getId();
                $payload['appPaymentMethod']['id'] = $existingAppPaymentMethod->getId();

                $media = $existing->getMedia();
                $originalMedia = $existingAppPaymentMethod->getOriginalMedia();
                if (($media === null && $originalMedia === null)
                    || ($media !== null && $originalMedia !== null && $originalMedia->getId() === $media->getId())
                ) {
                    // user has not overwritten media, set new
                    $payload['mediaId'] = $payload['appPaymentMethod']['originalMediaId'];
                }
            } else {
                $payload['afterOrderEnabled'] = true;
                $payload['mediaId'] = $payload['appPaymentMethod']['originalMediaId'];
            }

            $upserts[] = $payload;
        }

        if ($upserts !== []) {
            $this->paymentMethodRepository->upsert($upserts, $context->context);
        }

        $this->deactivatePaymentMethods($existingPaymentMethods, $context->context);
    }

    private function deactivatePaymentMethods(PaymentMethodCollection $toBeDisabled, Context $context): void
    {
        $updates = array_reduce($toBeDisabled->getElements(), static function (array $acc, PaymentMethodEntity $paymentMethod): array {
            $appPaymentMethod = $paymentMethod->getAppPaymentMethod();
            if (!$appPaymentMethod) {
                return $acc;
            }

            if (!$paymentMethod->getActive() && !$appPaymentMethod->getAppId()) {
                return $acc;
            }

            $acc[] = [
                'id' => $paymentMethod->getId(),
                'active' => false,
                'appPaymentMethod' => [
                    'id' => $appPaymentMethod->getId(),
                    'appId' => null,
                ],
            ];

            return $acc;
        }, []);

        if (empty($updates)) {
            return;
        }

        $this->paymentMethodRepository->update($updates, $context);
    }

    private function getExistingPaymentMethods(string $appName, string $appId, Context $context): PaymentMethodCollection
    {
        $criteria = new Criteria();
        $criteria->addAssociation('media');
        $criteria->addAssociation('appPaymentMethod.originalMedia');
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('appPaymentMethod.appName', $appName),
            new EqualsFilter('appPaymentMethod.appId', $appId),
        ]));

        return $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($criteria) {
            return $this->paymentMethodRepository->search($criteria, $context)->getEntities();
        });
    }

    private function getMediaId(Filesystem $fs, string $appName, PaymentMethod $paymentMethod, Context $context, ?AppPaymentMethodEntity $existing): ?string
    {
        if (!$iconPath = $paymentMethod->getIcon()) {
            return null;
        }

        if (!$fs->has($iconPath)) {
            return null;
        }

        $fileName = \sprintf('payment_app_%s_%s', $appName, $paymentMethod->getIdentifier());
        $icon = $fs->read($iconPath);
        $extension = pathinfo($paymentMethod->getIcon() ?? '', \PATHINFO_EXTENSION);
        $mimeType = $this->mimeDetector->detectMimeTypeFromBuffer($icon);
        $mediaId = $existing?->getOriginalMediaId();

        if (!$mimeType) {
            return null;
        }

        return $this->mediaService->saveFile(
            $icon,
            $extension,
            $mimeType,
            $fileName,
            $context,
            PaymentMethodDefinition::ENTITY_NAME,
            $mediaId,
            false
        );
    }
}
