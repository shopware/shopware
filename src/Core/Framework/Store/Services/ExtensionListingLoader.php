<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use GuzzleHttp\Exception\ClientException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Exception\StoreApiException;
use Shopware\Core\Framework\Store\Struct\ExtensionCollection;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;

/**
 * @internal
 */
#[Package('merchant-services')]
class ExtensionListingLoader
{
    public function __construct(private readonly StoreClient $client)
    {
    }

    public function load(ExtensionCollection $localCollection, Context $context): ExtensionCollection
    {
        $this->addUpdateInformation($localCollection, $context);
        $this->addStoreInformation($localCollection, $context);

        return $this->sortCollection($localCollection);
    }

    private function addStoreInformation(ExtensionCollection $localCollection, Context $context): void
    {
        try {
            $storeExtensions = $this->client->listMyExtensions($localCollection, $context);
        } catch (\Throwable) {
            return;
        }

        foreach ($storeExtensions->getElements() as $storeExtension) {
            if ($localCollection->has($storeExtension->getName())) {
                /** @var ExtensionStruct $localExtension */
                $localExtension = $localCollection->get($storeExtension->getName());
                $localExtension->setId($storeExtension->getId());
                $localExtension->setIsTheme($storeExtension->isTheme());
                $localExtension->setStoreExtension($storeExtension);

                $localExtension->setStoreLicense($storeExtension->getStoreLicense());
                $localExtension->setNotices($storeExtension->getNotices());

                if ($storeExtension->getDescription()) {
                    $localExtension->setDescription($storeExtension->getDescription());
                }

                if ($storeExtension->getShortDescription()) {
                    $localExtension->setShortDescription($storeExtension->getShortDescription());
                }

                $localExtension->setIcon($storeExtension->getIcon());
                $localExtension->setLabel($storeExtension->getLabel());

                if ($storeExtension->getLatestVersion()) {
                    $localExtension->setLatestVersion($storeExtension->getLatestVersion());
                    $localExtension->setUpdateSource($storeExtension->getUpdateSource());
                }

                continue;
            }

            $localCollection->set($storeExtension->getName(), $storeExtension);
        }
    }

    private function sortCollection(ExtensionCollection $collection): ExtensionCollection
    {
        $collection->sort(fn (ExtensionStruct $a, ExtensionStruct $b) => strcmp($a->getLabel(), $b->getLabel()));

        $sortedCollection = new ExtensionCollection();

        // Sorted order: active, installed, all others
        foreach ($collection->getElements() as $extension) {
            if ($extension->getActive()) {
                $sortedCollection->set($extension->getName(), $extension);
                $collection->remove($extension->getName());
            }
        }

        foreach ($collection->getElements() as $extension) {
            if ($extension->getInstalledAt()) {
                $sortedCollection->set($extension->getName(), $extension);
                $collection->remove($extension->getName());
            }
        }

        foreach ($collection->getElements() as $extension) {
            $sortedCollection->set($extension->getName(), $extension);
        }

        return $sortedCollection;
    }

    private function addUpdateInformation(ExtensionCollection $localCollection, Context $context): void
    {
        try {
            $updates = $this->client->getExtensionUpdateList($localCollection, $context);
        } catch (StoreApiException | ClientException) {
            return;
        }

        foreach ($updates as $update) {
            $extension = $localCollection->get($update->getName());

            if (!$extension) {
                continue;
            }

            $extension->setLatestVersion($update->getVersion());
            $extension->setUpdateSource(ExtensionStruct::SOURCE_STORE);
        }
    }
}
