/**
 * @package admin
 */

import Vuex from 'vuex';
import SettingsItemStore from './settings-item.store';

describe('src/app/state/settings.store.js', () => {
    let store = null;

    beforeEach(() => {
        store = new Vuex.Store(SettingsItemStore);
    });

    afterEach(() => {
        store.replaceState({
            settingsGroups: {
                general: [],
                customer: [],
                automation: [],
                localization: [],
                content: [],
                commerce: [],
                system: [],
                plugins: [],
                shop: [],
            },
        });
    });

    it('adds a new item to the specified group', () => {
        const settingsItem = {
            group: 'general',
            name: 'newSetting',
        };

        store.commit('addItem', settingsItem);

        expect(store.state.settingsGroups.general).toHaveLength(1);
        expect(store.state.settingsGroups.general[0]).toEqual(settingsItem);
    });

    it('does not add a duplicate item to the specified group', () => {
        const settingsItem = {
            group: 'general',
            name: 'newSetting',
        };

        store.commit('addItem', settingsItem);
        store.commit('addItem', settingsItem);

        expect(store.state.settingsGroups.general).toHaveLength(1);
    });

    it('creates a new group dynamically if the group does not exist', () => {
        const settingsItem = {
            group: 'customGroup',
            name: 'customSetting',
        };

        store.commit('addItem', settingsItem);

        expect(store.state.settingsGroups[settingsItem.group]).toBeDefined();
        expect(store.state.settingsGroups[settingsItem.group]).toHaveLength(1);
        expect(store.state.settingsGroups[settingsItem.group][0]).toEqual(settingsItem);
    });

    it('handles group as a function', () => {
        const settingsItem = {
            group: () => 'dynamicGroup',
            name: 'dynamicSetting',
        };

        store.commit('addItem', settingsItem);

        expect(store.state.settingsGroups[settingsItem.group()]).toBeDefined();
        expect(store.state.settingsGroups[settingsItem.group()]).toHaveLength(1);
        expect(store.state.settingsGroups[settingsItem.group()][0]).toEqual({
            group: settingsItem.group,
            name: 'dynamicSetting',
        });
    });

    it('throws an error if the group is undefined', () => {
        const settingsItem = {
            group: undefined,
            name: 'orphanSetting',
        };

        expect(() => store.commit('addItem', settingsItem)).toThrow('Group is undefined or invalid');
    });
});
