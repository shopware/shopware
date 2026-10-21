import './extension/sw-fixture-detail';

Shopware.Component.register('sw-fixture-detail', () => import('./page/sw-fixture-detail'));
Shopware.Component.register('sw-fixture-typed', () => import('./component/sw-fixture-typed'));
Shopware.Component.extend('sw-fixture-extended', 'sw-fixture-detail', () => import('./component/sw-fixture-extended'));
Shopware.Component.register('sw-fixture-private', () => import('./component/sw-fixture-private'));
Shopware.Component.register('sw-fixture-sfc', () => import('./component/sw-fixture-sfc.vue'));
Shopware.Component.register('sw-fixture-missing', () => import('./component/sw-fixture-missing'));

Shopware.Component.override('sw-fixture-unknown', {
    methods: {
        onUnknown() {},
    },
});
