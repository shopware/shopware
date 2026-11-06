/**
 * @sw-package framework
 */

import { componentMembers, jsApiMigration, memberUsage, serviceMethod, upgradeGuide } from '../helpers';

export default jsApiMigration({
    id: 'js-api.changed-defaults',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'These Administration defaults changed, check code that relies on the old ones.',
    references: [upgradeGuide('UPGRADE-6.8.md#changed-administration-defaults')],
    usage: [
        ...componentMembers({
            components: ['sw-media-library'],
            members: ['sorting'],
            message: 'The media library sorts by createdAt descending by default.',
        }),
        ...componentMembers({
            components: ['sw-media-preview-v2'],
            members: ['buildSourceSet'],
            message: 'Media and thumbnail URLs come percent-encoded from the server.',
        }),
        serviceMethod({
            service: 'mailService',
            method: 'sendMailTemplate',
            missingArgument: 7,
            message:
                'sendMailTemplate() no longer fills templateData from the mail template type. Pass templateData explicitly.',
        }),
        memberUsage({
            from: '*.mailTemplateType.templateData',
            message: 'The stored templateData of mail template types is removed.',
        }),
    ],
});
