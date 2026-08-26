/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

async function wrapperFactory({ propsData, privileges = [], platform = 'Linux x86_64' }) {
    return mount(await wrapTestComponent('sw-shortcut-overview-item', { sync: true }), {
        props: { ...propsData },
        global: {
            mocks: {
                $device: {
                    getPlatform: () => platform,
                },
            },
            provide: {
                acl: {
                    can: (key) => {
                        if (!key) {
                            return true;
                        }

                        return privileges.includes(key);
                    },
                },
            },
        },
    });
}

describe('app/component/utils/sw-shortcut-overview-item', () => {
    it('should show the shortcut overview item', async () => {
        const wrapper = await wrapperFactory({
            propsData: {
                title: 'Clear cache',
                content: 'ALT-C',
            },
        });

        const shortcut = wrapper.findAll('kbd');
        expect(shortcut).toHaveLength(2);
        expect(shortcut.at(0).text()).toBe('Alt');
        expect(shortcut.at(1).text()).toBe('C');

        const title = wrapper.find('.sw-shortcut-overview-item__title');
        expect(title.text()).toBe('Clear cache');
    });

    it('should split the key combinations into multiple kbd elements', async () => {
        const wrapper = await wrapperFactory({
            propsData: {
                title: 'Clear cache',
                content: 'D C',
            },
        });

        const shortcut = wrapper.findAll('kbd');
        expect(shortcut).toHaveLength(2);
        expect(shortcut.at(0).text()).toBe('D');
        expect(shortcut.at(1).text()).toBe('C');
    });

    it('should show Mac key symbols', async () => {
        const wrapper = await wrapperFactory({
            platform: 'MacIntel',
            propsData: {
                title: 'Save detail view',
                content: 'ALT-S CONTROL-S CMD-S Shift-?',
            },
        });

        const shortcut = wrapper.findAll('kbd');
        expect(shortcut.map((key) => key.text())).toEqual([
            '⌥',
            'S',
            '⌃',
            'S',
            '⌘',
            'S',
            '⇧',
            '?',
        ]);
        expect(shortcut.at(0).attributes('aria-label')).toBe('Option');
        expect(shortcut.at(4).attributes('aria-label')).toBe('Command');
    });

    it('should show Windows key labels', async () => {
        const wrapper = await wrapperFactory({
            platform: 'Win32',
            propsData: {
                title: 'Save detail view',
                content: 'CONTROL-S CMD-S ALT-C',
            },
        });

        const shortcut = wrapper.findAll('kbd');
        expect(shortcut.map((key) => key.text())).toEqual([
            'Ctrl',
            'S',
            '⊞',
            'S',
            'Alt',
            'C',
        ]);
    });

    it('should show Linux key labels', async () => {
        const wrapper = await wrapperFactory({
            platform: 'Linux x86_64',
            propsData: {
                title: 'Save detail view',
                content: 'CONTROL-S CMD-S ALT-C',
            },
        });

        const shortcut = wrapper.findAll('kbd');
        expect(shortcut.map((key) => key.text())).toEqual([
            'Ctrl',
            'S',
            'Super',
            'S',
            'Alt',
            'C',
        ]);
    });

    it('should show tab key labels with the tab symbol', async () => {
        const wrapper = await wrapperFactory({
            propsData: {
                title: 'Move focus backward',
                content: 'Tab Shift-Tab Enter',
            },
        });

        const shortcut = wrapper.findAll('kbd');
        expect(shortcut.map((key) => key.text())).toEqual([
            '⇥ Tab',
            'Shift',
            '⇥ Tab',
            'Enter',
        ]);
        expect(shortcut.map((key) => key.attributes('aria-label'))).toEqual([
            'Tab',
            'Shift',
            'Tab',
            'Enter',
        ]);
    });

    it('should not show the item because the privilege does not exist', async () => {
        const wrapper = await wrapperFactory({
            propsData: {
                title: 'Clear cache',
                content: 'D C',
                privilege: 'system.clear_cache',
            },
            privileges: [],
        });

        const item = wrapper.find('.sw-shortcut-overview-item');
        expect(item.exists()).toBeFalsy();
    });

    it('should show the item because the privilege does exists', async () => {
        const wrapper = await wrapperFactory({
            propsData: {
                title: 'Clear cache',
                content: 'D C',
                privilege: 'system.clear_cache',
            },
            privileges: ['system.clear_cache'],
        });

        const item = wrapper.find('.sw-shortcut-overview-item');
        expect(item.exists()).toBeTruthy();
    });
});
