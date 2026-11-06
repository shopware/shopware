/**
 * @sw-package framework
 */

import { componentMember, jsApiMigration, serviceMethod, upgradeGuide } from '../helpers';

export default jsApiMigration({
    id: 'js-api.system-config-schema',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'sw-system-config uses schema, a list of tabs with their cards, and systemConfigApiService.getSchema().',
    references: [upgradeGuide('UPGRADE-6.8.md#removal-of-deprecated-config-data-property-in-sw-system-config-component')],
    usage: [
        componentMember({ components: ['sw-system-config'], member: 'config', to: 'schema' }),
        componentMember({ components: ['sw-system-config'], member: 'isSyncingFromSchema', to: 'schema' }),
        componentMember({ components: ['sw-system-config'], member: 'isSyncingFromConfig', to: 'schema' }),
        componentMember({ components: ['sw-system-config'], member: 'schemaToConfig', to: 'schema' }),
        componentMember({ components: ['sw-system-config'], member: 'configToSchema', to: 'schema' }),
        componentMember({ components: ['sw-system-config'], member: 'assignConfigIds', to: 'schema' }),
        componentMember({ components: ['sw-system-config'], member: 'getSchemaForDomain', to: 'schema' }),
        componentMember({ components: ['sw-system-config'], member: 'getTabIndexByElementOverlap', to: 'schema' }),
        serviceMethod({ service: 'systemConfigApiService', method: 'getConfig', to: 'getSchema(domain)' }),
    ],
});
