<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation;

use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelCollection;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelDefinition;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelEntity;
use Shopware\Core\Checkout\DocumentV2\DocumentCollection;
use Shopware\Core\Checkout\DocumentV2\DocumentDefinition;
use Shopware\Core\Checkout\DocumentV2\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\SalesChannel\AbstractDocumentRoute;
use Shopware\Core\Checkout\DocumentV2\SalesChannel\DocumentRoute;
use Shopware\Core\Checkout\DocumentV2\Service\ReferenceInvoiceLoader;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderedDocument;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class ClassAliasRegistry
{
    /**
     * @var array<non-empty-string, class-string>
     */
    public const ALIASES = [
        'Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection' => DocumentBaseConfigCollection::class,
        'Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition' => DocumentBaseConfigDefinition::class,
        'Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity' => DocumentBaseConfigEntity::class,
        'Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelCollection' => DocumentBaseConfigSalesChannelCollection::class,
        'Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelDefinition' => DocumentBaseConfigSalesChannelDefinition::class,
        'Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelEntity' => DocumentBaseConfigSalesChannelEntity::class,
        'Shopware\Core\Checkout\Document\DocumentCollection' => DocumentCollection::class,
        'Shopware\Core\Checkout\Document\DocumentDefinition' => DocumentDefinition::class,
        'Shopware\Core\Checkout\Document\DocumentEntity' => DocumentEntity::class,
        'Shopware\Core\Checkout\Document\SalesChannel\AbstractDocumentRoute' => AbstractDocumentRoute::class,
        'Shopware\Core\Checkout\Document\SalesChannel\DocumentRoute' => DocumentRoute::class,
        'Shopware\Core\Checkout\Document\Service\ReferenceInvoiceLoader' => ReferenceInvoiceLoader::class,
        'Shopware\Core\Checkout\Document\Renderer\RenderedDocument' => RenderedDocument::class,
    ];

    private function __construct()
    {
    }
}
