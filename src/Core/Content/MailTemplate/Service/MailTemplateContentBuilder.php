<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
#[Package('after-sales')]
class MailTemplateContentBuilder
{
    /**
     * Attaches header and footer to the given mail template bodies.
     *
     * @param array{contentPlain: string, contentHtml: string} $content
     *
     * @return array{contentPlain: string, contentHtml: string}
     */
    public function build(array $content, ?SalesChannelEntity $salesChannel): array
    {
        $mailHeaderFooter = $salesChannel?->getMailHeaderFooter();

        if ($mailHeaderFooter === null) {
            return $content;
        }

        $headerPlain = $mailHeaderFooter->getTranslation('headerPlain') ?? '';
        \assert(\is_string($headerPlain));
        $footerPlain = $mailHeaderFooter->getTranslation('footerPlain') ?? '';
        \assert(\is_string($footerPlain));
        $headerHtml = $mailHeaderFooter->getTranslation('headerHtml') ?? '';
        \assert(\is_string($headerHtml));
        $footerHtml = $mailHeaderFooter->getTranslation('footerHtml') ?? '';
        \assert(\is_string($footerHtml));

        return [
            'contentPlain' => \sprintf('%s%s%s', $headerPlain, $content['contentPlain'], $footerPlain),
            'contentHtml' => \sprintf('%s%s%s', $headerHtml, $content['contentHtml'], $footerHtml),
        ];
    }
}
