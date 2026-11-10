/**
 * @sw-package framework
 */

import { apiUsageMigration, componentMember, memberUsage, templateAttribute, upgradeGuide } from '../helpers';

const MESSAGE =
    'The mail template preview moved to sw-mail-template-preview-modal in 6.8. ' +
    'It previews with mailService.previewMailTemplate() and sends test mails with sendMailTemplate() in test mode.';

export default apiUsageMigration({
    id: 'api.mail-template-preview',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The mail template preview modal was extracted into sw-mail-template-preview-modal, and the mail template lists search on their own.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#mail-template-preview-component-changes'),
        upgradeGuide('UPGRADE-6.8.md#mail-template-component-changes'),
    ],
    usage: [
        componentMember({
            components: ['sw-mail-template-detail'],
            names: [
                'hasTemplateData',
                'mailPreviewContent',
                'replaceContent',
            ],
            message: MESSAGE,
        }),
        componentMember({
            components: ['sw-mail-template-index'],
            names: [
                'term',
                'getList',
            ],
            message: 'The mail template lists search on their own in 6.8. It is removed without replacement.',
        }),
        memberUsage({ service: 'mailService', properties: ['testMailTemplate', 'buildRenderPreview'], message: MESSAGE }),
        templateAttribute({
            elements: [
                'sw-mail-template-list',
                'sw-mail-header-footer-list',
            ],
            names: ['search-term'],
            message: 'The mail template lists no longer have the searchTerm prop in 6.8. Remove the binding.',
        }),
    ],
});
