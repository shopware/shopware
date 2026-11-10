/**
 * @sw-package framework
 */

import { apiUsageMigration, componentMember, memberUsage, upgradeGuide } from '../helpers';

export default apiUsageMigration({
    id: 'api.sw-system-config.config',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'sw-system-config only has the schema with tabs and cards, and systemConfigApiService.getConfig() and its route are removed.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removal-of-deprecated-config-data-property-in-sw-system-config-component'),
    ],
    usage: [
        componentMember({
            components: ['sw-system-config'],
            names: [
                'config',
                'isSyncingFromSchema',
                'isSyncingFromConfig',
                'schemaToConfig',
                'configToSchema',
                'assignConfigIds',
                'getSchemaForDomain',
                'getTabIndexByElementOverlap',
            ],
            message:
                'sw-system-config no longer has config and its config/schema sync helpers in 6.8. Use schema, a list of tabs with their cards.',
        }),
        memberUsage({
            service: 'systemConfigApiService',
            properties: ['getConfig'],
            message:
                'systemConfigApiService.getConfig() is removed in 6.8. Use getSchema(domain), and decorate getSchema instead of getConfig.',
        }),
    ],
});
