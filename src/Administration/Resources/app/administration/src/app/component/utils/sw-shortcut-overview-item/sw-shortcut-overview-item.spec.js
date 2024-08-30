/**
 * @package admin
 */
import { mount } from '@vue/test-utils';

async function wrapperFactory({ propsData, privileges = [] }) {
    return mount(await wrapTestComponent('sw-shortcut-overview-item', { sync: true }), {
        props: { ...propsData },
        global: {
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
    it('should show the shortcout overview item', async () => {
        const wrapper = await wrapperFactory({
            propsData: {
                title: 'Clear cache',
                content: 'ALT-C',
            },
        });

        const shortcut = wrapper.findAll('kbd');
        expect(shortcut).toHaveLength(1);
        expect(shortcut.at(0).text()).toBe('ALT-C');

        const title = wrapper.find('.sw-shortcut-overview-item__title');
        expect(title.text()).toBe('Clear cache');
    });

    it('should split the key combinations into multiple kbd´s', async () => {
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

    it('should not show the item because the privilege does not exists', async () => {
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
