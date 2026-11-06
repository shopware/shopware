/**
 * @sw-package framework
 */

import { componentMembers, jsApiMigration, memberUsage, upgradeGuide } from '../helpers';

export default jsApiMigration({
    id: 'js-api.mail-template-preview',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The mail template preview moved to sw-mail-template-preview-modal, which previews with mailService.previewMailTemplate().',
    references: [upgradeGuide('UPGRADE-6.8.md#mail-template-preview-component-changes')],
    usage: [
        ...componentMembers({
            components: ['sw-mail-template-detail'],
            members: ['hasTemplateData', 'mailPreviewContent', 'replaceContent'],
            to: 'sw-mail-template-preview-modal',
        }),
        ...componentMembers({ components: ['sw-mail-template-index'], members: ['term', 'getList'] }),
        memberUsage({ from: '*.testMailTemplate', to: 'mailService.sendMailTemplate() in test mode' }),
        memberUsage({ from: '*.buildRenderPreview', to: 'mailService.previewMailTemplate()' }),
    ],
});
