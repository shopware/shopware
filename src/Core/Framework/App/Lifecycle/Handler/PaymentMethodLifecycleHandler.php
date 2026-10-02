<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Handler;

use League\MimeTypeDetection\FinfoMimeTypeDetector;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodEntity;
use Shopware\Core\Framework\App\AppHandlerIdentifier;
use Shopware\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Manifest\Xml\PaymentMethod\PaymentMethod;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class PaymentMethodLifecycleHandler extends AbstractLifecycleHandler
{
    private readonly FinfoMimeTypeDetector $mimeDetector;

    /**
     * @param EntityRepository<PaymentMethodCollection> $paymentMethodRepository
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly EntityRepository $paymentMethodRepository,
        private readonly EntityRepository $mediaRepository,
        private readonly MediaService $mediaService,
    ) {
        $this->mimeDetector = new FinfoMimeTypeDetector();
    }

    public function install(AppPersistContext $context): void
    {
        $this->persist($context);
    }

    public function update(AppPersistContext $context): void
    {
        $this->persist($context);
    }

    public function activate(AppActivationContext $context): void
    {
        $this->updateActiveState($context->app->getId(), $context->context, false, true);
    }

    public function deactivate(AppActivationContext $context): void
    {
        $this->updateActiveState($context->app->getId(), $context->context, true, false);
    }

    private function persist(AppPersistContext $context): void
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

        // the media of every icon is looked up once for all payment methods instead of once per payment method, and
        // only when an icon actually needs it
        $mediaIdsByFileName = null;
        $resolveMediaId = function (string $fileName) use (&$mediaIdsByFileName, $paymentMethods, $appName, $context): ?string {
            $mediaIdsByFileName ??= $this->fetchMediaIdsByFileName(array_map(
                fn (PaymentMethod $paymentMethod): string => $this->iconFileName($appName, $paymentMethod),
                $paymentMethods
            ), $context->context);

            return $mediaIdsByFileName[$fileName] ?? null;
        };

        foreach ($paymentMethods as $paymentMethod) {
            $payload = $paymentMethod->toArray($context->defaultLocale);
            $payload['handlerIdentifier'] = AppHandlerIdentifier::build($appName, $paymentMethod->getIdentifier());
            $payload['technicalName'] = \sprintf('payment_%s_%s', $appName, $paymentMethod->getIdentifier());

            $existing = $existingPaymentMethods->filterByProperty('handlerIdentifier', $payload['handlerIdentifier'])->first();
            $existingAppPaymentMethod = $existing ? $existing->getAppPaymentMethod() : null;

            $payload['appPaymentMethod']['appId'] = $appId;
            $payload['appPaymentMethod']['appName'] = $appName;
            $payload['appPaymentMethod']['originalMediaId'] = $this->getMediaId($context->appFilesystem, $appName, $paymentMethod, $context->context, $existingAppPaymentMethod, $resolveMediaId);

            if ($existing && $existingAppPaymentMethod) {
                $existingPaymentMethods->remove($existing->getId());

                $payload['id'] = $existing->getId();
                $payload['appPaymentMethod']['id'] = $existingAppPaymentMethod->getId();

                $payload = $this->removeAlreadyTranslatedTexts($payload, $existing);

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

        if ($updates === []) {
            return;
        }

        $this->paymentMethodRepository->update($updates, $context);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function removeAlreadyTranslatedTexts(array $payload, PaymentMethodEntity $existing): array
    {
        $translated = [];
        foreach ($existing->getTranslations() ?? [] as $translation) {
            // DAL resolves translation payload keys through the translation code, not the formatting locale
            $translationCode = $translation->getLanguage()?->getTranslationCode()?->getCode();
            if ($translationCode !== null) {
                $translated[$translationCode] = true;
            }
        }

        foreach (['name', 'description'] as $field) {
            $texts = \is_array($payload[$field] ?? null) ? array_diff_key($payload[$field], $translated) : [];

            if ($texts === []) {
                unset($payload[$field]);

                continue;
            }

            $payload[$field] = $texts;
        }

        return $payload;
    }

    private function getExistingPaymentMethods(string $appName, string $appId, Context $context): PaymentMethodCollection
    {
        $criteria = new Criteria();
        $criteria->addAssociation('media');
        $criteria->addAssociation('appPaymentMethod.originalMedia');
        $criteria->addAssociation('translations.language.translationCode');
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('appPaymentMethod.appName', $appName),
            new EqualsFilter('appPaymentMethod.appId', $appId),
        ]));

        return $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($criteria) {
            return $this->paymentMethodRepository->search($criteria, $context)->getEntities();
        });
    }

    private function updateActiveState(string $appId, Context $context, bool $currentActiveState, bool $newActiveState): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appPaymentMethod.appId', $appId));
        $criteria->addFilter(new EqualsFilter('active', $currentActiveState));

        $paymentMethods = $this->paymentMethodRepository->searchIds($criteria, $context)->getPrimaryKeyData();
        foreach ($paymentMethods as &$paymentMethod) {
            $paymentMethod['active'] = $newActiveState;
        }
        unset($paymentMethod);

        $this->paymentMethodRepository->update($paymentMethods, $context);
    }

    private function getMediaId(Filesystem $fs, string $appName, PaymentMethod $paymentMethod, Context $context, ?AppPaymentMethodEntity $existing, \Closure $resolveMediaId): ?string
    {
        if (!$iconPath = $paymentMethod->getIcon()) {
            return null;
        }

        if (!$fs->has($iconPath)) {
            return null;
        }

        $fileName = $this->iconFileName($appName, $paymentMethod);
        $icon = $fs->read($iconPath);
        $extension = pathinfo($paymentMethod->getIcon() ?? '', \PATHINFO_EXTENSION);
        $mimeType = $this->mimeDetector->detectMimeTypeFromBuffer($icon);
        $mediaId = $existing?->getOriginalMediaId() ?? $resolveMediaId($fileName);

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

    private function iconFileName(string $appName, PaymentMethod $paymentMethod): string
    {
        return \sprintf('payment_app_%s_%s', $appName, $paymentMethod->getIdentifier());
    }

    /**
     * @param list<string> $fileNames
     *
     * @return array<string, string> file name to media id
     */
    private function fetchMediaIdsByFileName(array $fileNames, Context $context): array
    {
        if ($fileNames === []) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('fileName', $fileNames));
        $criteria->addFields(['id', 'fileName']);

        $mediaIds = [];
        foreach ($this->mediaRepository->search($criteria, $context) as $media) {
            $fileName = $media->get('fileName');

            if (\is_string($fileName)) {
                $mediaIds[$fileName] = $media->getUniqueIdentifier();
            }
        }

        return $mediaIds;
    }
}
