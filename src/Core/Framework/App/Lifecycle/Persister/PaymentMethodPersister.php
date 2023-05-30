<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use League\MimeTypeDetection\FinfoMimeTypeDetector;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodEntity;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\PaymentMethod;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class PaymentMethodPersister
{
    private FinfoMimeTypeDetector $mimeDetector;

    public function __construct(
        private readonly EntityRepository $paymentMethodRepository,
        private readonly MediaService $mediaService,
        private readonly AbstractAppLoader $appLoader,
    ) {
        $this->mimeDetector = new FinfoMimeTypeDetector();
    }

    public function updatePaymentMethods(Manifest $manifest, string $appId, string $defaultLocale, Context $context): void
    {
        $existingPaymentMethods = $this->getExistingPaymentMethods($manifest->getMetadata()->getName(), $appId, $context);

        $payments = $manifest->getPayments();
        $paymentMethods = $payments !== null ? $payments->getPaymentMethods() : [];
        $upserts = [];

        foreach ($paymentMethods as $paymentMethod) {
            $payload = $paymentMethod->toArray($defaultLocale);
            $payload['handlerIdentifier'] = sprintf('app\\%s_%s', $manifest->getMetadata()->getName(), $paymentMethod->getIdentifier());

            /** @var PaymentMethodEntity|null $existing */
            $existing = $existingPaymentMethods->filterByProperty('handlerIdentifier', $payload['handlerIdentifier'])->first();
            $existingAppPaymentMethod = $existing ? $existing->getAppPaymentMethod() : null;

            $payload['appPaymentMethod']['appId'] = $appId;
            $payload['appPaymentMethod']['appName'] = $manifest->getMetadata()->getName();
            $payload['appPaymentMethod']['originalMediaId'] = $this->getMediaId($manifest, $paymentMethod, $context, $existingAppPaymentMethod);

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

        if (!empty($upserts)) {
            $this->paymentMethodRepository->upsert($upserts, $context);
        }

        $this->deactivatePaymentMethods($existingPaymentMethods, $context);
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
            /** @var PaymentMethodCollection $paymentMethods */
            $paymentMethods = $this->paymentMethodRepository->search($criteria, $context)->getEntities();

            return $paymentMethods;
        });
    }

    private function getMediaId(Manifest $manifest, PaymentMethod $paymentMethod, Context $context, ?AppPaymentMethodEntity $existing): ?string
    {
        if (!$iconPath = $paymentMethod->getIcon()) {
            return null;
        }

        $icon = $this->appLoader->loadFile($manifest->getPath(), $iconPath);
        if (!$icon) {
            return null;
        }

        $fileName = sprintf('payment_app_%s_%s', $manifest->getMetadata()->getName(), $paymentMethod->getIdentifier());
        $extension = pathinfo($paymentMethod->getIcon() ?? '', \PATHINFO_EXTENSION);
        $mimeType = $this->mimeDetector->detectMimeTypeFromBuffer($icon);
        $mediaId = $existing !== null ? $existing->getOriginalMediaId() : null;

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
