/**
 * @sw-package framework
 *
 * Proves the `shopware:*` modules are usable from a spec, and that what they hand out is the very object
 * the global `Shopware` holds rather than a copy of it. `setup.ts` registers them for every spec.
 */

import { createId, object } from 'shopware:utils';
import { warn, error } from 'shopware:utils/debug';
import debugNamespace from 'shopware:utils/debug';
import { Criteria, EntityCollection } from 'shopware:data';
import CriteriaClass from 'shopware:data/Criteria';
import swFormFieldMixin from 'shopware:mixins/sw-form-field';
import ruleContainerMixin from 'shopware:mixins/ruleContainer';
import useNotificationStore from 'shopware:stores/notification';
import useSystemStore from 'shopware:stores/system';
import composables, { useListing } from 'shopware:composables';
import useListingDefault from 'shopware:composables/useListing';
import directUseListing from 'src/app/composables/use-listing';
import 'src/core/public-component/register';
import { definePublicComponent } from 'shopware:framework/public-component';
import { definePublicComponent as directDefinePublicComponent } from 'src/core/public-component';

// `Shopware` is declared `const` on the global, which puts it outside the keys `replaceProperty` accepts.
const globalObject = global as unknown as { Shopware: unknown };

describe('build/shopware-modules: the shopware:* modules in Jest', () => {
    describe('root imports', () => {
        it('export the members of their branch', () => {
            // ACT
            const imported = { createId, object, Criteria, EntityCollection };

            // ASSERT
            expect(imported.createId).toBe(Shopware.Utils.createId);
            expect(imported.object).toBe(Shopware.Utils.object);
            expect(imported.Criteria).toBe(Shopware.Data.Criteria);
            expect(imported.EntityCollection).toBe(Shopware.Data.EntityCollection);
        });

        it('export the composables the Administration itself imports', () => {
            // ACT
            const imported = { useListing, composables };

            // ASSERT
            expect(imported.useListing).toBe(directUseListing);
            expect(imported.composables.useListing).toBe(Shopware.Composables.useListing);
        });

        it('export working members', () => {
            // ACT
            const id = createId();
            const criteria = new Criteria(1, 25);

            // ASSERT
            expect(id).toHaveLength(32);
            expect(criteria.limit).toBe(25);
        });
    });

    describe('subpaths', () => {
        it('export the members of one namespace, and the namespace as default', () => {
            // ACT
            const imported = { warn, error, debugNamespace };

            // ASSERT
            expect(imported.warn).toBe(Shopware.Utils.debug.warn);
            expect(imported.error).toBe(Shopware.Utils.debug.error);
            expect(imported.debugNamespace.warn).toBe(Shopware.Utils.debug.warn);
        });

        it('export a DAL class as default', () => {
            // ACT
            const imported = CriteriaClass;

            // ASSERT
            expect(imported).toBe(Shopware.Data.Criteria);
        });

        it('export a composable as default', () => {
            // ACT
            const imported = useListingDefault;

            // ASSERT
            expect(imported).toBe(directUseListing);
        });

        it('export a registered mixin as default', () => {
            // ACT
            const imported = swFormFieldMixin;

            // ASSERT
            expect(imported).toBe(Shopware.Mixin.getByName('sw-form-field'));
        });

        it('take the registry key verbatim, camelCase included', () => {
            // ACT
            const imported = ruleContainerMixin;

            // ASSERT
            expect(imported).toBe(Shopware.Mixin.getByName('ruleContainer'));
        });

        it('export a store as a composable, resolved per call', () => {
            // ACT
            const notificationStore = useNotificationStore();
            const systemStore = useSystemStore();

            // ASSERT
            expect(typeof useNotificationStore).toBe('function');
            expect(notificationStore).toBe(Shopware.Store.get('notification'));
            expect(systemStore).toBe(Shopware.Store.get('system'));
        });

        it('export the index.ts exports of a published mini-module', () => {
            // ACT
            const imported = definePublicComponent;

            // ASSERT
            expect(imported).toBe(directDefinePublicComponent);
        });
    });

    describe('a spec that replaces the global Shopware', () => {
        it('reads a named export off the global at access time, not at import', () => {
            // ARRANGE
            const shopware = jest.replaceProperty(globalObject, 'Shopware', { Utils: { createId: () => 'first' } });
            const first = createId();

            // ACT
            shopware.replaceValue({ Utils: { createId: () => 'second' } });

            // ASSERT
            expect(first).toBe('first');
            expect(createId()).toBe('second');
        });

        it('resolves a store on every call', () => {
            // ARRANGE
            const firstStore = { source: 'first' };
            const secondStore = { source: 'second' };
            const shopware = jest.replaceProperty(globalObject, 'Shopware', { Store: { get: () => firstStore } });
            const first = useNotificationStore();

            // ACT
            shopware.replaceValue({ Store: { get: () => secondStore } });

            // ASSERT
            expect(first).toBe(firstStore);
            expect(useNotificationStore()).toBe(secondStore);
        });
    });
});
