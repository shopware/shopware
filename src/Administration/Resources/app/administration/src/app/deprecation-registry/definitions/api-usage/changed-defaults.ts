/**
 * @sw-package framework
 */

import { apiUsageMigration, componentMember, memberUsage, upgradeGuide } from '../helpers';

/**
 * Behaviour that changed with the 6.8 feature flag. The codemod points at overrides that rely on the
 * old behaviour; the media library sorting is left out, because overriding it is no hint of a problem.
 */
export default apiUsageMigration({
    id: 'api.changed-administration-defaults',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'Media URLs come percent-encoded from the server, and sendMailTemplate() no longer fills the template data on its own.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#changed-administration-defaults'),
    ],
    usage: [
        componentMember({
            components: ['sw-media-preview-v2'],
            names: ['buildSourceSet'],
            message:
                'Media and thumbnail URLs come percent-encoded from the server in 6.8. Remove your own encodeURI() to avoid double encoding.',
        }),
        memberUsage({
            service: 'mailService',
            properties: ['sendMailTemplate'],
            withoutArgument: 7,
            message:
                'sendMailTemplate() no longer fills templateData from the mail template type in 6.8. Pass templateData explicitly.',
        }),
        memberUsage({
            object: '*.mailTemplateType',
            properties: ['templateData'],
            message: 'The stored templateData of mail template types is removed in 6.8. Build the template data yourself.',
        }),
    ],
});
