/**
 * @package admin
 */

describe('src/app/state/settings.store.js', () => {
    const store = Shopware.Store.get('settingsItems');

    beforeEach(() => {
        store.$reset();
    });

    it('adds a new item to the specified group', () => {
        const settingsItem = {
            group: 'general',
            name: 'newSetting',
        };

        store.addItem(settingsItem);

        expect(store.settingsGroups.general).toHaveLength(1);
        expect(store.settingsGroups.general[0]).toEqual(settingsItem);
    });

    it('does not add a duplicate item to the specified group', () => {
        const settingsItem = {
            group: 'general',
            name: 'newSetting',
        };

        store.addItem(settingsItem);
        store.addItem(settingsItem);

        expect(store.settingsGroups.general).toHaveLength(1);
    });

    it('creates a new group dynamically if the group does not exist', () => {
        const settingsItem = {
            group: 'customGroup',
            name: 'customSetting',
        };

        store.addItem(settingsItem);

        expect(store.settingsGroups[settingsItem.group]).toBeDefined();
        expect(store.settingsGroups[settingsItem.group]).toHaveLength(1);
        expect(store.settingsGroups[settingsItem.group][0]).toEqual(settingsItem);
    });

    it('handles group as a function', () => {
        const settingsItem = {
            group: () => 'dynamicGroup',
            name: 'dynamicSetting',
        };

        store.addItem(settingsItem);

        expect(store.settingsGroups[settingsItem.group()]).toBeDefined();
        expect(store.settingsGroups[settingsItem.group()]).toHaveLength(1);
        expect(store.settingsGroups[settingsItem.group()][0]).toEqual({
            group: settingsItem.group,
            name: 'dynamicSetting',
        });
    });

    it('throws an error if the group is undefined', () => {
        const settingsItem = {
            group: undefined,
            name: 'orphanSetting',
        };

        expect(() => store.addItem(settingsItem)).toThrow('Group is undefined or invalid');
    });
});
