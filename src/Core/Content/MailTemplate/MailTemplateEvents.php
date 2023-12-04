<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate;

use Shopware\Core\Framework\Log\Package;

#[Package('services-settings')]
class MailTemplateEvents
{
    final public const MAIL_TEMPLATE_WRITTEN_EVENT = 'mail_template.written';

    final public const MAIL_TEMPLATE_DELETED_EVENT = 'mail_template.deleted';

    final public const MAIL_TEMPLATE_LOADED_EVENT = 'mail_template.loaded';

    final public const MAIL_TEMPLATE_SEARCH_RESULT_LOADED_EVENT = 'mail_template.search.result.loaded';

    final public const MAIL_TEMPLATE_AGGREGATION_LOADED_EVENT = 'mail_template.aggregation.result.loaded';

    final public const MAIL_TEMPLATE_ID_SEARCH_RESULT_LOADED_EVENT = 'mail_template.id.search.result.loaded';

    final public const MAIL_TEMPLATE_TRANSLATION_WRITTEN_EVENT = 'mail_template_translation.written';

    final public const MAIL_TEMPLATE_TRANSLATION_DELETED_EVENT = 'mail_template_translation.deleted';

    final public const MAIL_TEMPLATE_TRANSLATION_LOADED_EVENT = 'mail_template_translation.loaded';

    final public const MAIL_TEMPLATE_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'mail_template_translation.search.result.loaded';

    final public const MAIL_TEMPLATE_TRANSLATION_AGGREGATION_LOADED_EVENT = 'mail_template_translation.aggregation.result.loaded';

    final public const MAIL_TEMPLATE_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'mail_template_translation.id.search.result.loaded';

    final public const MAIL_TEMPLATE_SALES_CHANNEL_WRITTEN_EVENT = 'mail_template_sales_channel.written';

    final public const MAIL_TEMPLATE_SALES_CHANNEL_DELETED_EVENT = 'mail_template_sales_channel.deleted';

    final public const MAIL_TEMPLATE_SALES_CHANNEL_LOADED_EVENT = 'mail_template_sales_channel.loaded';

    final public const MAIL_TEMPLATE_SALES_CHANNEL_SEARCH_RESULT_LOADED_EVENT = 'mail_template_sales_channel.search.result.loaded';

    final public const MAIL_TEMPLATE_SALES_CHANNEL_AGGREGATION_LOADED_EVENT = 'mail_template_sales_channel.aggregation.result.loaded';

    final public const MAIL_TEMPLATE_SALES_CHANNEL_ID_SEARCH_RESULT_LOADED_EVENT = 'mail_template_sales_channel.id.search.result.loaded';

    final public const MAIL_TEMPLATE_MEDIA_WRITTEN_EVENT = 'mail_template_media.written';

    final public const MAIL_TEMPLATE_MEDIA_DELETED_EVENT = 'mail_template_media.deleted';

    final public const MAIL_TEMPLATE_MEDIA_LOADED_EVENT = 'mail_template_media.loaded';

    final public const MAIL_TEMPLATE_MEDIA_SEARCH_RESULT_LOADED_EVENT = 'mail_template_media.search.result.loaded';

    final public const MAIL_TEMPLATE_MEDIA_AGGREGATION_LOADED_EVENT = 'mail_template_media.aggregation.result.loaded';

    final public const MAIL_TEMPLATE_MEDIA_ID_SEARCH_RESULT_LOADED_EVENT = 'mail_template_media.id.search.result.loaded';

    final public const MAIL_HEADER_FOOTER_WRITTEN_EVENT = 'mail_header_footer.written';

    final public const MAIL_HEADER_FOOTER_DELETED_EVENT = 'mail_header_footer.deleted';

    final public const MAIL_HEADER_FOOTER_LOADED_EVENT = 'mail_header_footer.loaded';

    final public const MAIL_HEADER_FOOTER_SEARCH_RESULT_LOADED_EVENT = 'mail_header_footer.search.result.loaded';

    final public const MAIL_HEADER_FOOTER_AGGREGATION_LOADED_EVENT = 'mail_header_footer.aggregation.result.loaded';

    final public const MAIL_HEADER_FOOTER_ID_SEARCH_RESULT_LOADED_EVENT = 'mail_header_footer.id.search.result.loaded';

    final public const MAIL_HEADER_FOOTER_TRANSLATION_WRITTEN_EVENT = 'mail_header_footer_translation.written';

    final public const MAIL_HEADER_FOOTER_TRANSLATION_DELETED_EVENT = 'mail_header_footer_translation.deleted';

    final public const MAIL_HEADER_FOOTER_TRANSLATION_LOADED_EVENT = 'mail_header_footer_translation.loaded';

    final public const MAIL_HEADER_FOOTER_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'mail_header_footer_translation.search.result.loaded';

    final public const MAIL_HEADER_FOOTER_TRANSLATION_AGGREGATION_LOADED_EVENT = 'mail_header_footer_translation.aggregation.result.loaded';

    final public const MAIL_HEADER_FOOTER_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'mail_header_footer_translation.id.search.result.loaded';

    final public const MAIL_HEADER_FOOTER_SALES_CHANNEL_WRITTEN_EVENT = 'mail_header_footer_sales_channel.written';

    final public const MAIL_HEADER_FOOTER_SALES_CHANNEL_DELETED_EVENT = 'mail_header_footer_sales_channel.deleted';

    final public const MAIL_HEADER_FOOTER_SALES_CHANNEL_LOADED_EVENT = 'mail_header_footer_sales_channel.loaded';

    final public const MAIL_HEADER_FOOTER_SALES_CHANNEL_SEARCH_RESULT_LOADED_EVENT = 'mail_header_footer_sales_channel.search.result.loaded';

    final public const MAIL_HEADER_FOOTER_SALES_CHANNEL_AGGREGATION_LOADED_EVENT = 'mail_header_footer_sales_channel.aggregation.result.loaded';

    final public const MAIL_HEADER_FOOTER_SALES_CHANNEL_ID_SEARCH_RESULT_LOADED_EVENT = 'mail_header_footer_sales_channel.id.search.result.loaded';
}
