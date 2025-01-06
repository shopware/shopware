<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Extension;

use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @experimental stableVersion:v6.7.0 feature:EXTENSION_SYSTEM
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<Response>
 */
#[Package('services-settings')]
final class SitemapFileExtension extends Extension
{
    public const NAME = 'sitemap.get-file';

    /**
     * @internal
     */
    public function __construct(
        /**
         * @public
         *
         * @description Allows you to access to the current request
         */
        public readonly Request $request,

        /**
         * @public
         *
         * @description Allows you to access to the current customer/sales-channel context
         */
        public readonly SalesChannelContext $context,

        /**
         * @public
         *
         * @description The file path of the requested sitemap file
         */
        public readonly string $filePath
    ) {
    }
}
