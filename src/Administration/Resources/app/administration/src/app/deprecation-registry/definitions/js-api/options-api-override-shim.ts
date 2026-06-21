import { jsApiMigration, reference, replaceApi } from '../helpers';

export default jsApiMigration({
    id: 'js-api.options-api-override-shim',
    deprecatedIn: '6.8.0',
    removedIn: '6.9.0',
    description:
        'Options API component overrides are deprecated for Composition API components. Use overrideComponentSetup for setup-based overrides.',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.8.md#composition-api-extension-and-override-system' }),
        reference({
            type: 'docs',
            target: 'https://developer.shopware.com/docs/resources/references/adr/2023-02-24-admin-composition-api-extension-system.html',
        }),
    ],
    usage: [
        replaceApi({
            from: 'Shopware.Component.override',
            to: 'Shopware.Component.overrideComponentSetup',
            fix: 'manual',
        }),
    ],
});
