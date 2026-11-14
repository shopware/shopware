/**
 * @sw-package framework
 *
 * Texts of the `mail-template-api` codemod. 6.8 removes the mail template APIs that the preview modal of
 * `sw-mail-template-detail` used before it moved to `sw-mail-template-preview-modal`, and the `searchTerm` prop of the
 * mail template lists. Taken from the `@deprecated tag:v6.8.0` markers in `src/core/service/api/mail.api.service.js`,
 * `MailActionController.php`, `sw-mail-template-detail`, `sw-mail-template-list` and `sw-mail-header-footer-list`.
 * The removed preview Twig blocks are covered by `twig-block-renames`.
 */

const BUILD =
    'mailService.buildRenderPreview() and /api/_action/mail-template/build are removed. Use ' +
    'mailService.previewMailTemplate(mailTemplateId, entities, templateData, salesChannelId) for a saved template or ' +
    'mailService.simulateMailTemplate(templateParts, eventName, salesChannelId) for unsaved content.';

const LISTS = ['sw-mail-template-list', 'sw-mail-header-footer-list'];

const SEARCH_TERM =
    'The searchTerm prop and watcher of sw-mail-template-list and sw-mail-header-footer-list are removed. ' +
    'The lists read the search term from the route through the listing mixin (this.term).';

module.exports = {
    id: 'mail-template-api',
    description: 'Marks the mail template service methods, routes and component members that 6.8 removes',
    calls: [
        {
            service: 'mailService',
            method: 'testMailTemplate',
            message:
                'mailService.testMailTemplate() is removed. Use sendMailTemplate(recipient, recipient, mailTemplate, ' +
                'mailTemplateMedia, salesChannelId, true, documentIds, {}, mailTemplateTypeId, mailTemplateId) and pass the ' +
                'template data instead of {} where the template needs it; 6.8 no longer falls back to ' +
                'mailTemplate.mailTemplateType.templateData.',
        },
        { service: 'mailService', method: 'buildRenderPreview', message: BUILD },
    ],
    literals: [
        { value: /\/_action\/mail-template\/build$/, message: BUILD },
        {
            value: /\/_action\/mail-template\/validate$/,
            message:
                '/api/_action/mail-template/validate is removed. Render the template with ' +
                '/api/_action/mail-template/preview (mailService.previewMailTemplate()) to validate it.',
        },
    ],
    members: [
        {
            component: 'sw-mail-template-detail',
            name: 'hasTemplateData',
            message:
                'sw-mail-template-detail.hasTemplateData is removed without replacement, together with the stored ' +
                'mail_template_type.template_data.',
        },
        {
            component: 'sw-mail-template-detail',
            name: 'mailPreviewContent',
            message:
                'sw-mail-template-detail.mailPreviewContent() is removed without replacement. The preview is rendered on ' +
                'the server by simulateMailPreview() and shown in sw-mail-template-preview-modal.',
        },
        {
            component: 'sw-mail-template-detail',
            name: 'replaceContent',
            message:
                'sw-mail-template-detail.replaceContent() is removed without replacement. The preview is rendered on ' +
                'the server by simulateMailPreview() and shown in sw-mail-template-preview-modal.',
        },
        { component: LISTS, name: 'searchTerm', kinds: ['props', 'watch'], message: SEARCH_TERM },
    ],
    /** Tags whose `search-term` attribute sets the removed prop. */
    searchTermTags: LISTS,
    searchTermAttributeMessage:
        'sw-mail-template-list and sw-mail-header-footer-list no longer have a search-term prop; they read the search ' +
        'term from the route. Remove this attribute.',
};
