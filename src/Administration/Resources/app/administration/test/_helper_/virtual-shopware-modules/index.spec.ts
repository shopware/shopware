/**
 * @sw-package framework
 *
 * Proves the `shopware:*` modules are usable from a spec, and that what they hand out is the very object
 * the global `Shopware` holds rather than a copy of it.
 */

import { createId, object, format } from 'shopware:utils';
import { Criteria, EntityCollection } from 'shopware:data';
import { swFormFieldMixin, removeApiErrorMixin, ruleContainerMixin } from 'shopware:mixins';
import { useNotificationStore, useSystemStore } from 'shopware:stores';

describe('shopware:* virtual modules', () => {
    describe('shopware:utils', () => {
        it('exports the functions of Shopware.Utils', () => {
            expect(createId).toBe(Shopware.Utils.createId);
            expect(object).toBe(Shopware.Utils.object);
            expect(format).toBe(Shopware.Utils.format);
        });

        it('exports working functions', () => {
            expect(createId()).toHaveLength(32);
        });
    });

    describe('shopware:data', () => {
        it('exports the classes of Shopware.Data', () => {
            expect(Criteria).toBe(Shopware.Data.Criteria);
            expect(EntityCollection).toBe(Shopware.Data.EntityCollection);
        });

        it('exports constructible classes', () => {
            expect(new Criteria(1, 25).limit).toBe(25);
        });
    });

    describe('shopware:mixins', () => {
        it('exports the registered mixins', () => {
            expect(swFormFieldMixin).toBe(Shopware.Mixin.getByName('sw-form-field'));
            expect(removeApiErrorMixin).toBe(Shopware.Mixin.getByName('remove-api-error'));
        });

        it('resolves a camelCase registry name that no kebab-case name matches', () => {
            expect(ruleContainerMixin).toBe(Shopware.Mixin.getByName('ruleContainer'));
        });
    });

    describe('shopware:stores', () => {
        it('exports a composable per registered store', () => {
            expect(useNotificationStore()).toBe(Shopware.Store.get('notification'));
            expect(useSystemStore()).toBe(Shopware.Store.get('system'));
        });

        it('resolves the store on call, not on import', () => {
            expect(typeof useNotificationStore).toBe('function');
        });
    });
});
