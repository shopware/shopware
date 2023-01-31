<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('sales-channel')]
class ProductExportHydrator extends EntityHydrator
{
    /**
     * @param array<string> $row
     *
     * @throws \Exception
     */
    protected function assign(EntityDefinition $definition, Entity $entity, string $root, array $row, Context $context): Entity
    {
        if (isset($row[$root . '.id'])) {
            $entity->id = Uuid::fromBytesToHex($row[$root . '.id']);
        }
        if (isset($row[$root . '.productStreamId'])) {
            $entity->productStreamId = Uuid::fromBytesToHex($row[$root . '.productStreamId']);
        }
        if (isset($row[$root . '.storefrontSalesChannelId'])) {
            $entity->storefrontSalesChannelId = Uuid::fromBytesToHex($row[$root . '.storefrontSalesChannelId']);
        }
        if (isset($row[$root . '.salesChannelId'])) {
            $entity->salesChannelId = Uuid::fromBytesToHex($row[$root . '.salesChannelId']);
        }
        if (isset($row[$root . '.salesChannelDomainId'])) {
            $entity->salesChannelDomainId = Uuid::fromBytesToHex($row[$root . '.salesChannelDomainId']);
        }
        if (isset($row[$root . '.currencyId'])) {
            $entity->currencyId = Uuid::fromBytesToHex($row[$root . '.currencyId']);
        }
        if (isset($row[$root . '.fileName'])) {
            $entity->fileName = $row[$root . '.fileName'];
        }
        if (isset($row[$root . '.accessKey'])) {
            $entity->accessKey = $row[$root . '.accessKey'];
        }
        if (isset($row[$root . '.encoding'])) {
            $entity->encoding = $row[$root . '.encoding'];
        }
        if (isset($row[$root . '.fileFormat'])) {
            $entity->fileFormat = $row[$root . '.fileFormat'];
        }
        if (isset($row[$root . '.includeVariants'])) {
            $entity->includeVariants = (bool) $row[$root . '.includeVariants'];
        }
        if (isset($row[$root . '.generateByCronjob'])) {
            $entity->generateByCronjob = (bool) $row[$root . '.generateByCronjob'];
        }
        if (isset($row[$root . '.isRunning'])) {
            $entity->isRunning = (bool) $row[$root . '.isRunning'];
        }
        if (isset($row[$root . '.generatedAt'])) {
            $entity->generatedAt = new \DateTimeImmutable($row[$root . '.generatedAt']);
        }
        if (isset($row[$root . '.interval'])) {
            $entity->interval = (int) $row[$root . '.interval'];
        }
        if (\array_key_exists($root . '.headerTemplate', $row)) {
            $entity->headerTemplate = $definition->decode('headerTemplate', self::value($row, $root, 'headerTemplate'));
        }
        if (\array_key_exists($root . '.bodyTemplate', $row)) {
            $entity->bodyTemplate = $definition->decode('bodyTemplate', self::value($row, $root, 'bodyTemplate'));
        }
        if (\array_key_exists($root . '.footerTemplate', $row)) {
            $entity->footerTemplate = $definition->decode('footerTemplate', self::value($row, $root, 'footerTemplate'));
        }
        if (isset($row[$root . '.pausedSchedule'])) {
            $entity->pausedSchedule = (bool) $row[$root . '.pausedSchedule'];
        }
        if (isset($row[$root . '.createdAt'])) {
            $entity->createdAt = new \DateTimeImmutable($row[$root . '.createdAt']);
        }
        if (isset($row[$root . '.updatedAt'])) {
            $entity->updatedAt = new \DateTimeImmutable($row[$root . '.updatedAt']);
        }
        $entity->productStream = $this->manyToOne($row, $root, $definition->getField('productStream'), $context);
        $entity->storefrontSalesChannel = $this->manyToOne($row, $root, $definition->getField('storefrontSalesChannel'), $context);
        $entity->salesChannel = $this->manyToOne($row, $root, $definition->getField('salesChannel'), $context);
        $entity->salesChannelDomain = $this->manyToOne($row, $root, $definition->getField('salesChannelDomain'), $context);
        $entity->currency = $this->manyToOne($row, $root, $definition->getField('currency'), $context);

        $this->translate($definition, $entity, $row, $root, $context, $definition->getTranslatedFields());
        $this->hydrateFields($definition, $entity, $root, $row, $context, $definition->getExtensionFields());

        return $entity;
    }
}
