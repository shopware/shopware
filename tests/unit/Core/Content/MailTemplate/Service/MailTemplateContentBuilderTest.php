<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterEntity;
use Shopware\Core\Content\MailTemplate\Service\MailTemplateContentBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
#[CoversClass(MailTemplateContentBuilder::class)]
#[Package('after-sales')]
class MailTemplateContentBuilderTest extends TestCase
{
    public function testBuildReturnsOriginalContentWithoutSalesChannel(): void
    {
        $builder = new MailTemplateContentBuilder();

        $content = [
            'contentPlain' => 'plain',
            'contentHtml' => '<p>html</p>',
        ];

        static::assertSame($content, $builder->build($content, null));
    }

    public function testBuildWrapsContentWithTranslatedHeaderAndFooter(): void
    {
        $builder = new MailTemplateContentBuilder();

        $mailHeaderFooter = new MailHeaderFooterEntity();
        $mailHeaderFooter->setTranslated([
            'headerHtml' => '<header>head</header>',
            'footerHtml' => '<footer>foot</footer>',
            'headerPlain' => 'head-',
            'footerPlain' => '-foot',
        ]);

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setMailHeaderFooter($mailHeaderFooter);

        $result = $builder->build([
            'contentPlain' => 'plain',
            'contentHtml' => '<p>html</p>',
        ], $salesChannel);

        static::assertSame('head-plain-foot', $result['contentPlain']);
        static::assertSame('<header>head</header><p>html</p><footer>foot</footer>', $result['contentHtml']);
    }

    public function testBuildFallsBackToEmptyStringsForMissingTranslations(): void
    {
        $builder = new MailTemplateContentBuilder();

        $mailHeaderFooter = new MailHeaderFooterEntity();
        $mailHeaderFooter->setTranslated([]);

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setMailHeaderFooter($mailHeaderFooter);

        $content = [
            'contentPlain' => 'plain',
            'contentHtml' => '<p>html</p>',
        ];

        static::assertSame($content, $builder->build($content, $salesChannel));
    }
}
