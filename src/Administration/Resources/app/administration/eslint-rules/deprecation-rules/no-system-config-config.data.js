/**
 * @sw-package framework
 *
 * The flat card config of `sw-system-config` and `systemConfigApiService.getConfig()`, which 6.8 removes in favor of
 * the tab schema and `getSchema()`, taken from the `@deprecated tag:v6.8.0` markers. `getSchema()` exists since
 * 6.7.16.0 and returns another shape, so the codemod only marks the spots. Slot props named `config` carry the
 * config values and stay.
 */

const SYNC = 'is removed without replacement. It only synced the removed config data with schema.';

module.exports = {
    id: 'system-config-schema',
    description: 'Marks the config data of sw-system-config and systemConfigApiService.getConfig(), which 6.8 replaces with schema and getSchema()',
    calls: [
        { service: 'systemConfigApiService', method: 'getConfig', message: 'systemConfigApiService.getConfig() is removed. Use getSchema(domain) (since 6.7.16.0); it returns tabs [{ name, title, cards }] instead of cards.' },
    ],
    members: [
        { component: 'sw-system-config', name: 'config', kinds: ['data', 'watch'], message: 'sw-system-config no longer has the flat config data or the config loop variable around sw_system_config_content_card. Use schema (tabs with cards, this.schema.flatMap((tab) => tab.cards)) or tab.cards inside the tab loop.' },
        { component: 'sw-system-config', name: 'isSyncingFromSchema', message: `sw-system-config.isSyncingFromSchema ${SYNC}` },
        { component: 'sw-system-config', name: 'isSyncingFromConfig', message: `sw-system-config.isSyncingFromConfig ${SYNC}` },
        { component: 'sw-system-config', name: 'getSchemaForDomain', message: 'sw-system-config.getSchemaForDomain() is removed. Use this.systemConfigApiService.getSchema(this.domain).' },
        { component: 'sw-system-config', name: 'assignConfigIds', message: `sw-system-config.assignConfigIds() ${SYNC}` },
        { component: 'sw-system-config', name: 'schemaToConfig', message: `sw-system-config.schemaToConfig() ${SYNC}` },
        { component: 'sw-system-config', name: 'getTabIndexByElementOverlap', message: `sw-system-config.getTabIndexByElementOverlap() ${SYNC}` },
        { component: 'sw-system-config', name: 'configToSchema', message: `sw-system-config.configToSchema() ${SYNC}` },
    ],
    /** Assignments to `getConfig` of the service, also inside `addServiceProviderDecorator('systemConfigApiService', …)`. */
    decorator: {
        service: 'systemConfigApiService',
        method: 'getConfig',
        message: 'sw-system-config no longer merges cards from a decorated systemConfigApiService.getConfig(). Decorate getSchema(domain) instead and return tabs [{ name, title, cards }].',
    },
    /** Matched in string and template literals. */
    routes: [
        { pattern: /system-config\/schema(?![\w-])/, message: 'The Admin API route /api/_action/system-config/schema is removed. Use /api/_action/system-config/get-schema; it returns tabs [{ name, title, cards }] instead of cards.' },
    ],
};
