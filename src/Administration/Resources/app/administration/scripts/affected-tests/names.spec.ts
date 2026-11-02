import { findPublicComponentName, findRegistrations, mentionsName } from './names';

describe('findRegistrations', () => {
    it('finds the literal names of every kind of registration', () => {
        // ARRANGE
        const source = `
            Shopware.Component.register('sw-foo', () => import('./sw-foo'));
            Component.extend('sw-foo-wide', 'sw-foo', () => import('./sw-foo-wide'));
            Shopware.Component.override("sw-bar", {});
            Shopware.Mixin.register('notification', {});
            Shopware.Service().register('priceService', () => {});
            Shopware.Application.addServiceProvider('acl', () => {});
            Shopware.Store.register({
                id: 'swOrderDetail',
            });
            Shopware.Filter.register('currency', () => {});
            Shopware.Directive.register('tooltip', {});
            Shopware.Module.register('sw-order', {});
        `;

        // ACT
        const registrations = findRegistrations(source);

        // ASSERT
        expect(registrations).toEqual({
            names: [
                { kind: 'component', name: 'sw-foo' },
                { kind: 'component', name: 'sw-foo-wide' },
                { kind: 'component', name: 'sw-bar' },
                { kind: 'mixin', name: 'notification' },
                { kind: 'service', name: 'priceService' },
                { kind: 'service', name: 'acl' },
                { kind: 'store', name: 'swOrderDetail' },
                { kind: 'filter', name: 'currency' },
                { kind: 'directive', name: 'tooltip' },
                { kind: 'module', name: 'sw-order' },
            ],
            dynamic: [],
        });
    });

    it('reports a registration whose name is computed', () => {
        // ACT
        const registrations = findRegistrations('Shopware.Application.addServiceProvider(serviceName, () => {});');

        // ASSERT
        expect(registrations).toEqual({ names: [], dynamic: ['addServiceProvider(serviceName'] });
    });

    it('ignores registrations in comments', () => {
        // ARRANGE
        const source = `
            /**
             * Shopware.Component.register(SwExample);
             */
            // Shopware.Service().register(name, factory);
            const url = 'https://example.com';
        `;

        // ACT
        const registrations = findRegistrations(source);

        // ASSERT
        expect(registrations).toEqual({ names: [], dynamic: [] });
    });
});

describe('findPublicComponentName', () => {
    it('reads the name from definePublicComponent()', () => {
        // ACT
        const name = findPublicComponentName(
            "export default definePublicComponent({\n    name: 'sw-example',\n    load: () => import('./src/sw-example'),\n});",
        );

        // ASSERT
        expect(name).toBe('sw-example');
    });

    it('returns null for a module that is no component', () => {
        // ACT
        const name = findPublicComponentName("export { default } from './src/service-names';");

        // ASSERT
        expect(name).toBeNull();
    });
});

describe('mentionsName', () => {
    it('matches the name as a whole word only', () => {
        // ACT
        const mentions = [
            "await wrapTestComponent('sw-foo')",
            '<sw-foo :value="1" />',
            "await wrapTestComponent('sw-foo-bar')",
            "wrapper.find('.sw-foo__label')",
        ].map((source) => mentionsName(source, 'sw-foo'));

        // ASSERT
        expect(mentions).toEqual([
            true,
            true,
            false,
            false,
        ]);
    });
});
