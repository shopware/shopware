<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use League\MimeTypeDetection\FinfoMimeTypeDetector;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\App\Aggregate\AppShippingMethod\AppShippingMethodEntity;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\ShippingMethod\ShippingMethod;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class ShippingMethodPersister
{
    private FinfoMimeTypeDetector $mimeDetector;

    /**
     * @param EntityRepository<ShippingMethodCollection>                  $shippingMethodRepository
     * @param EntityRepository<EntityCollection<AppShippingMethodEntity>> $appShippingMethodRepository
     * @param EntityRepository<MediaCollection>                           $mediaRepository
     */
    public function __construct(
        private readonly EntityRepository $shippingMethodRepository,
        private readonly EntityRepository $appShippingMethodRepository,
        private readonly EntityRepository $mediaRepository,
        private readonly MediaService $mediaService,
        private readonly AbstractAppLoader $appLoader,
    ) {
        $this->mimeDetector = new FinfoMimeTypeDetector();
    }

    public function updateShippingMethods(
        Manifest $manifest,
        string $appId,
        string $defaultLocale,
        Context $context
    ): void {
        $appName = $manifest->getMetadata()->getName();
        $manifestShipments = $manifest->getShippingMethods();
        $manifestShippingMethods = $manifestShipments?->getShippingMethods() ?? [];

        $existingAppShippingMethods = $this->getExistingAppShippingMethods($appName, $context);
        $existingShippingMethods = new ShippingMethodCollection();
        foreach ($existingAppShippingMethods as $existingAppShippingMethod) {
            $existingShippingMethod = $existingAppShippingMethod->getShippingMethod();
            if (!$existingShippingMethod instanceof ShippingMethodEntity) {
                continue;
            }

            $existingShippingMethods->add($existingShippingMethod);
        }

        $shippingMethodsToUpdate = [];

        foreach ($manifestShippingMethods as $manifestShippingMethod) {
            $payload = $manifestShippingMethod->toArray($defaultLocale);
            $payload['technicalName'] = \sprintf('shipping_%s_%s', $manifest->getMetadata()->getName(), $manifestShippingMethod->getIdentifier());

            $existingAppShippingMethod = $existingAppShippingMethods->filterByProperty('identifier', $manifestShippingMethod->getIdentifier())->first();

            if ($existingAppShippingMethod) {
                $payload['appShippingMethod']['id'] = $existingAppShippingMethod->getId();
            }

            $payload['appShippingMethod']['appId'] = $appId;
            $payload['appShippingMethod']['appName'] = $appName;

            $shippingMethodEntity = $existingAppShippingMethod?->getShippingMethod();
            if ($shippingMethodEntity) {
                $payload['id'] = $shippingMethodEntity->getId();
                unset(
                    $payload['name'],
                    $payload['description'],
                    $payload['icon'],
                    $payload['position'],
                    $payload['active'],
                    $payload['deliveryTime'],
                );
                $existingShippingMethods->remove($shippingMethodEntity->getId());
            } else {
                $payload['appShippingMethod']['originalMediaId'] = $this->getIconId($manifest, $manifestShippingMethod, $context);
                $payload['mediaId'] = $payload['appShippingMethod']['originalMediaId'];
            }

            $shippingMethodsToUpdate[] = $payload;
        }

        if ($shippingMethodsToUpdate !== []) {
            $this->shippingMethodRepository->upsert($shippingMethodsToUpdate, $context);
        }

        $this->deactivateOldShippingMethods($existingShippingMethods, $context);
    }

    /**
     * @return EntityCollection<AppShippingMethodEntity>
     */
    private function getExistingAppShippingMethods(
        string $appName,
        Context $context
    ): EntityCollection {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appName', $appName));
        $criteria->addAssociation('shippingMethod');

        return $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($criteria) {
            return $this->appShippingMethodRepository->search($criteria, $context)->getEntities();
        });
    }

    private function deactivateOldShippingMethods(
        ShippingMethodCollection $shippingMethods,
        Context $context
    ): void {
        $shippingMethodsToUpdate = [];
        foreach ($shippingMethods as $shippingMethod) {
            $shippingMethodsToUpdate[] = [
                'id' => $shippingMethod->getId(),
                'active' => false,
            ];
        }

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($shippingMethodsToUpdate): void {
            $this->shippingMethodRepository->update($shippingMethodsToUpdate, $context);
        });
    }

    private function getIconId(Manifest $manifest, ShippingMethod $shippingMethod, Context $context): ?string
    {
        $iconPath = $shippingMethod->getIcon();
        if (!$iconPath) {
            return null;
        }

        $icon = $this->appLoader->loadFile($manifest->getPath(), $iconPath);
        if (!$icon) {
            return null;
        }

        $fileName = sprintf('shipping_app_%s_%s', $manifest->getMetadata()->getName(), $shippingMethod->getIdentifier());
        $extension = pathinfo($iconPath, \PATHINFO_EXTENSION);
        $mimeType = $this->mimeDetector->detectMimeTypeFromBuffer($icon);

        if (!$mimeType) {
            return null;
        }

        return $this->mediaService->saveFile(
            $icon,
            $extension,
            $mimeType,
            $fileName,
            $context,
            ShippingMethodDefinition::ENTITY_NAME,
            $this->checkFileExists($fileName, $context),
            false
        );
    }

    private function checkFileExists(string $fileName, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('fileName', $fileName));
        $result = $this->mediaRepository->searchIds($criteria, $context);

        if ($result->getTotal() <= 0) {
            return null;
        }

        return $result->firstId();
    }
}
