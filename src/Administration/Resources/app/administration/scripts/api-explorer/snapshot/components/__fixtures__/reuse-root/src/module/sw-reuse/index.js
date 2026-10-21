Shopware.Component.register('sw-reuse-typed', () => import('./component/sw-reuse-typed'));
Shopware.Component.register('sw-reuse-plain', () => import('./component/sw-reuse-plain'));
Shopware.Component.override('sw-reuse-plain', () => import('./extension/sw-reuse-plain'));
