import { packageMigration, reference, renamePackage } from '../helpers';

export default packageMigration({
    id: 'package.admin-extension-sdk',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'The @shopware-ag/admin-extension-sdk package is deprecated. Use @shopware-ag/meteor-admin-sdk instead.',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.7.md#administration' }),
    ],
    usage: [
        renamePackage({
            from: '@shopware-ag/admin-extension-sdk',
            to: '@shopware-ag/meteor-admin-sdk',
        }),
    ],
});
