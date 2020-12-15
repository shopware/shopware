<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Store\Search\ExtensionCriteria;
use Shopware\Core\Framework\Store\Struct\ExtensionCollection;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;

abstract class AbstractExtensionDataProvider
{
    abstract public function getListing(ExtensionCriteria $criteria, Context $context): ExtensionCollection;

    abstract public function getListingFilters(Context $context): array;

    abstract public function getExtensionDetails(int $id, Context $context): ExtensionStruct;

    abstract public function getReviews(int $extensionId, ExtensionCriteria $criteria, Context $context): array;

    abstract public function getInstalledExtensions(Context $context): ExtensionCollection;

    abstract public function getAppEntityFromTechnicalName(string $technicalName, Context $context): AppEntity;

    abstract public function getAppEntityFromId(string $id, Context $context): AppEntity;

    abstract protected function getDecorated(): AbstractExtensionDataProvider;
}
