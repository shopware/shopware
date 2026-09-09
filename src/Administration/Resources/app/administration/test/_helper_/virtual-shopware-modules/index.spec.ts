/**
 * @sw-package framework
 *
 * Proves the `shopware:*` modules are usable from a spec, and that what they hand out is the very object
 * the global `Shopware` holds rather than a copy of it.
 */

import { createId, object } from 'shopware:utils';
import debug, { warn, error } from 'shopware:utils/debug';
import { Criteria, EntityCollection } from 'shopware:data';
import CriteriaClass from 'shopware:data/Criteria';
import swFormFieldMixin from 'shopware:mixins/sw-form-field';
import ruleContainerMixin from 'shopware:mixins/ruleContainer';
import useNotificationStore from 'shopware:stores/notification';
import useSystemStore from 'shopware:stores/system';

describe('shopware:* virtual modules', () => {
    describe('barrels', () => {
        it('export the members of their branch', () => {
            expect(createId).toBe(Shopware.Utils.createId);
            expect(object).toBe(Shopware.Utils.object);
            expect(Criteria).toBe(Shopware.Data.Criteria);
            expect(EntityCollection).toBe(Shopware.Data.EntityCollection);
        });

        it('export working members', () => {
            expect(createId()).toHaveLength(32);
            expect(new Criteria(1, 25).limit).toBe(25);
        });
    });

    describe('subpaths', () => {
        it('export the members of one namespace, and the namespace as default', () => {
            expect(warn).toBe(Shopware.Utils.debug.warn);
            expect(error).toBe(Shopware.Utils.debug.error);
            expect(debug).toBe(Shopware.Utils.debug);
        });

        it('export a DAL class as default', () => {
            expect(CriteriaClass).toBe(Shopware.Data.Criteria);
        });

        it('export a registered mixin as default', () => {
            expect(swFormFieldMixin).toBe(Shopware.Mixin.getByName('sw-form-field'));
        });

        it('take the registry key verbatim, camelCase included', () => {
            expect(ruleContainerMixin).toBe(Shopware.Mixin.getByName('ruleContainer'));
        });

        it('export a store as a composable, resolved per call', () => {
            expect(typeof useNotificationStore).toBe('function');
            expect(useNotificationStore()).toBe(Shopware.Store.get('notification'));
            expect(useSystemStore()).toBe(Shopware.Store.get('system'));
        });
    });
});
