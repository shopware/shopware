/**
 * @sw-package framework
 */

import { componentMembers, jsApiMigration, serviceMethod, upgradeGuide } from '../helpers';

export default jsApiMigration({
    id: 'js-api.system-config-schema',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'sw-system-config uses schema, a list of tabs with their cards, and systemConfigApiService.getSchema().',
    references: [upgradeGuide('UPGRADE-6.8.md#removal-of-deprecated-config-data-property-in-sw-system-config-component')],
    usage: [
        ...componentMembers({
            components: ['sw-system-config'],
            members: [
                'config',
                'isSyncingFromSchema',
                'isSyncingFromConfig',
                'schemaToConfig',
                'configToSchema',
                'assignConfigIds',
                'getSchemaForDomain',
                'getTabIndexByElementOverlap',
            ],
            to: 'schema',
        }),
        serviceMethod({ service: 'systemConfigApiService', method: 'getConfig', to: 'getSchema(domain)' }),
    ],
});
