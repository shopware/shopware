/**
 * @sw-package framework
 *
 * Administration behaviour that changes behind the `v6.8.0.0` feature flag and becomes the default in 6.8, taken
 * from the `Feature.isActive('v6.8.0.0')` branches:
 * - the default media sorting of `sw-media-library` (fileName asc → createdAt desc); an override that declares
 *   `sorting` itself is not affected,
 * - `sw-media-preview-v2` no longer encodes thumbnail URLs, because the server encodes them,
 * - `mailService.sendMailTemplate()` no longer falls back to `mailTemplateType.templateData`, and
 *   `sw-order-send-document-modal` no longer writes the order into it.
 *
 * `declarations` are only marked where an override declares them, not on every `this.<name>`.
 */

const MEDIA_LIBRARY_SORTING = 'The default sorting of sw-media-library changes from fileName asc to createdAt desc in 6.8. Set sorting explicitly if this override relies on the old order.';

module.exports = {
    id: 'flag-gated-behaviour',
    description: 'Marks code that relies on Administration behaviour that 6.8 changes behind the v6.8.0.0 flag',
    declarations: [
        { component: 'sw-media-library', name: 'nextMediaCriteria', kinds: ['computed'], message: MEDIA_LIBRARY_SORTING },
        { component: 'sw-media-preview-v2', name: 'buildSourceSet', kinds: ['methods'], message: 'sw-media-preview-v2.buildSourceSet() no longer runs encodeURI() on thumbnail URLs in 6.8, because the server encodes them. Use thumbnail.url as it is.' },
        { component: 'sw-order-send-document-modal', name: 'onMailTemplateChange', kinds: ['methods'], message: 'sw-order-send-document-modal.onMailTemplateChange() no longer writes the order into mailTemplate.mailTemplateType.templateData in 6.8. The preview loads the order on the server through previewMailTemplate() entities.' },
    ],
    encodeThumbnailUrl: {
        message: 'Thumbnail URLs are encoded by the server in 6.8, so encodeURI() encodes them twice. Use thumbnail.url as it is.',
    },
    sendMailTemplate: {
        service: 'mailService',
        method: 'sendMailTemplate',
        templateDataIndex: 7,
        message: 'sendMailTemplate() no longer fills templateData from mailTemplateType.templateData. Pass templateData explicitly or use getDataAndSendMailTemplate().',
    },
};
