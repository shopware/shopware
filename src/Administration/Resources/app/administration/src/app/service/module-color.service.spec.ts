/**
 * @sw-package framework
 */
import {
    MODULE_COLOR_NAMES,
    NEUTRAL_MODULE_COLOR,
    applyGroupColors,
    isModuleColorName,
    moduleColorToken,
    resolveDeclaredModuleColor,
    resolveManifestModuleColor,
} from 'src/app/service/module-color.service';
import type { ColorableNavigationEntry } from 'src/app/service/module-color.service';

describe('src/app/service/module-color.service', () => {
    beforeEach(() => {
        jest.spyOn(console, 'warn').mockImplementation(() => {});
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    describe('color names', () => {
        it.each([...MODULE_COLOR_NAMES])('should map the color name "%s" to its token', (name) => {
            expect(isModuleColorName(name)).toBe(true);
            expect(resolveDeclaredModuleColor(name, 'sw-group')).toBe(`var(--sw-module-color-${name})`);
        });

        it('should fall back to the neutral icon color for an unknown name', () => {
            expect(resolveDeclaredModuleColor('teal', 'sw-group')).toBe(NEUTRAL_MODULE_COLOR);
            expect(console.warn).toHaveBeenCalledWith(
                '[ModuleColor]',
                expect.stringContaining('unknown module color "teal"'),
                expect.any(String),
            );
        });

        it('should pass a raw color value through and warn once per source', () => {
            expect(resolveDeclaredModuleColor('#57D9A3', 'sw-legacy')).toBe('#57D9A3');
            expect(resolveDeclaredModuleColor('#57D9A3', 'sw-legacy')).toBe('#57D9A3');

            expect(console.warn).toHaveBeenCalledTimes(1);
            expect(console.warn).toHaveBeenCalledWith(
                '[ModuleColor]',
                expect.stringContaining('raw color value "#57D9A3"'),
                expect.any(String),
                expect.any(String),
            );
        });

        it('should pass a raw custom property through', () => {
            expect(resolveDeclaredModuleColor('var(--color-pink-500)', 'sw-legacy')).toBe('var(--color-pink-500)');
        });

        it('should leave an entry without a declared color undefined', () => {
            expect(resolveDeclaredModuleColor(undefined, 'sw-group')).toBeUndefined();
        });
    });

    describe('applyGroupColors', () => {
        it('should give every entry of a group the color of its first-level entry', () => {
            const [
                group,
                child,
                grandChild,
            ] = applyGroupColors([
                { id: 'sw-catalogue', color: 'green' },
                { id: 'sw-product', parent: 'sw-catalogue' },
                { id: 'sw-product-variants', parent: 'sw-product' },
            ]);

            expect(group.color).toBe(moduleColorToken('green'));
            expect(child.color).toBe(moduleColorToken('green'));
            expect(grandChild.color).toBe(moduleColorToken('green'));
        });

        it('should let an entry that declares a color keep it inside a colored group', () => {
            const [
                ,
                child,
            ] = applyGroupColors([
                { id: 'sw-catalogue', color: 'green' },
                { id: 'swag-product', parent: 'sw-catalogue', color: 'purple' },
            ]);

            expect(child.color).toBe(moduleColorToken('purple'));
        });

        it('should pass the color of a declaring entry down to its own children', () => {
            const [
                ,
                child,
                grandChild,
            ] = applyGroupColors([
                { id: 'sw-catalogue', color: 'green' },
                { id: 'swag-product', parent: 'sw-catalogue', color: 'purple' },
                { id: 'swag-product-detail', parent: 'swag-product' },
            ]);

            expect(child.color).toBe(moduleColorToken('purple'));
            expect(grandChild.color).toBe(moduleColorToken('purple'));
        });

        it('should keep the raw color value a child declares itself', () => {
            const [
                ,
                child,
            ] = applyGroupColors([
                { id: 'swag-group' },
                { id: 'swag-module', parent: 'swag-group', color: '#57D9A3' },
            ]);

            expect(child.color).toBe('#57D9A3');
        });

        it('should identify an entry without an id by its path', () => {
            const [
                ,
                child,
            ] = applyGroupColors([
                { id: 'sw-order', color: 'purple' },
                { path: 'sw.order.index', parent: 'sw-order' },
            ]);

            expect(child.color).toBe(moduleColorToken('purple'));
        });

        it('should keep its own color when its group is not registered', () => {
            const [entry] = applyGroupColors([{ id: 'swag-module', parent: 'swag-missing-group', color: 'pink' }]);

            expect(entry.color).toBe(moduleColorToken('pink'));
        });

        it('should not loop on entries that are their own ancestor', () => {
            const [
                first,
                second,
            ] = applyGroupColors<ColorableNavigationEntry>([
                { id: 'a', parent: 'b' },
                { id: 'b', parent: 'a' },
            ]);

            expect(first.color).toBeUndefined();
            expect(second.color).toBeUndefined();
        });

        it('should leave a group without a declared color uncolored', () => {
            const [
                group,
                child,
            ] = applyGroupColors<ColorableNavigationEntry>([
                { id: 'swag-group' },
                { id: 'swag-module', parent: 'swag-group' },
            ]);

            expect(group.color).toBeUndefined();
            expect(child.color).toBeUndefined();
        });
    });

    describe('resolveManifestModuleColor', () => {
        beforeEach(() => {
            // Only a core module may own a first-level navigation entry, so a group needs one.
            // eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
            Shopware.Module.register('sw-settings', {
                type: 'core',
                name: 'settings',
                title: 'Settings',
                routes: { index: { component: 'sw-settings-index', path: 'index' } },
                navigation: [{ id: 'sw-settings', label: 'Settings', color: 'slate' }],
            });

            // eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
            Shopware.Module.register('sw-catalogue-host', {
                type: 'core',
                name: 'catalogueHost',
                title: 'Catalogue',
                routes: { index: { component: 'sw-catalogue-index', path: 'index' } },
                navigation: [{ id: 'sw-catalogue', label: 'Catalogue', color: 'green' }],
            });

            // eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
            Shopware.Module.register('swag-product', {
                type: 'plugin',
                name: 'swagProduct',
                title: 'Swag product',
                routes: { index: { component: 'swag-product-index', path: 'index' } },
                navigation: [
                    {
                        id: 'swag-product',
                        label: 'Swag product',
                        parent: 'sw-catalogue',
                        path: 'swag.product.index',
                    },
                ],
            });
        });

        it('should resolve a module through the group of its navigation entry', () => {
            const manifest = Shopware.Module.getModuleRegistry().get('swag-product')?.manifest;

            expect(resolveManifestModuleColor(manifest)).toBe(moduleColorToken('green'));
        });

        it('should resolve a module that is only reachable through the settings list', () => {
            expect(resolveManifestModuleColor({ name: 'tax', settingsItem: { group: 'shop' } })).toBe(
                moduleColorToken('slate'),
            );
        });

        it('should fall back to the color a module outside every group declares', () => {
            expect(resolveManifestModuleColor({ name: 'salesChannel', color: 'green' })).toBe(moduleColorToken('green'));
        });

        it('should leave a module without any color undefined', () => {
            expect(resolveManifestModuleColor({ name: 'login' })).toBeUndefined();
        });
    });
});
