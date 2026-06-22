import { jsApiMigration, memberCall, reference } from '../helpers';

export default jsApiMigration({
    id: 'js-api.sw-product-detail-variants.load-config-setting-groups',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The loadConfigSettingGroups() method in sw-product-detail-variants is deprecated and should be removed from customizations.',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.7.md#administration' }),
    ],
    usage: [
        memberCall({ from: 'this.loadConfigSettingGroups', to: null, fix: 'manual' }),
    ],
});
