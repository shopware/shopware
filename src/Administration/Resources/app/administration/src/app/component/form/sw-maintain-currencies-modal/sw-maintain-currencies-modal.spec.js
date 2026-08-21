/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

const currencies = [
    {
        isSystemDefault: true,
        id: '123',
        name: 'Euro',
        translated: {
            name: 'Euro',
        },
    },
    {
        isSystemDefault: false,
        id: '124',
        name: 'Dollar',
        translated: {
            name: 'Euro',
        },
    },
];

const prices = [
    {
        currencyId: '123',
    },
];

async function createWrapper(propOverrides = {}) {
    return mount(await wrapTestComponent('sw-maintain-currencies-modal', { sync: true }), {
        global: {
            stubs: {
                'sw-data-grid': await wrapTestComponent('sw-data-grid', {
                    sync: true,
                }),
                'sw-inheritance-switch': await wrapTestComponent('sw-inheritance-switch', { sync: true }),
                'sw-data-grid-skeleton': true,
                'sw-list-price-field': true,
                'sw-checkbox-field': true,
                'sw-context-menu-item': true,
                'sw-context-button': true,
                'sw-data-grid-settings': true,
                'sw-data-grid-column-boolean': true,
                'sw-data-grid-inline-edit': true,
                'router-link': true,
                'sw-provide': { template: `<slot/>`, inheritAttrs: false },
            },
        },
        props: {
            currencies: currencies,
            prices: prices,
            defaultPrice: {
                gross: 0,
                linked: true,
                net: 0,
                currencyId: '124',
            },
            taxRate: {},
            ...propOverrides,
        },
    });
}

describe('src/app/component/form/sw-maintain-currencies-modal', () => {
    it('should load all currencies when none are passed in', async () => {
        const search = jest.fn(() => Promise.resolve([]));
        const createSpy = jest.spyOn(Shopware.Service('repositoryFactory'), 'create').mockImplementation(() => ({ search }));

        await createWrapper({ currencies: [] });
        await flushPromises();

        expect(search).toHaveBeenCalledTimes(1);
        expect(search.mock.calls[0][0].getLimit()).toBe(500);

        createSpy.mockRestore();
    });

    it('should be able to remove inheritance', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const inheritanceSwitch = wrapper.find('.sw-inheritance-switch');
        expect(inheritanceSwitch.isVisible()).toBe(true);
        const icon = inheritanceSwitch.find('.mt-icon');
        expect(icon.isVisible()).toBe(true);

        // check if switch show inheritance
        expect(inheritanceSwitch.classes()).toContain('sw-inheritance-switch--is-inherited');
        expect(wrapper.vm.prices).toHaveLength(1);

        // click on switch
        await icon.trigger('click');
        await flushPromises();
        expect(wrapper.vm.prices).toHaveLength(2);

        await wrapper.vm.onApply();
        expect(wrapper.emitted('modal-close')).toBeTruthy();
    });

    it('should be able to restore inheritance', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const inheritanceSwitch = wrapper.find('.sw-inheritance-switch');
        expect(inheritanceSwitch.isVisible()).toBe(true);
        const icon = inheritanceSwitch.find('.mt-icon');
        expect(icon.isVisible()).toBe(true);

        // check if switch show inheritance
        expect(inheritanceSwitch.classes()).toContain('sw-inheritance-switch--is-not-inherited');
        expect(wrapper.vm.prices).toHaveLength(2);

        // click on switch
        await icon.trigger('click');
        await flushPromises();
        expect(wrapper.emitted('update-prices')).toBeTruthy();
    });
});
