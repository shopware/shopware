/**
 * @package buyers-experience
 */
import { createLocalVue, shallowMount } from '@vue/test-utils';
import swSettingsSearchSearchableContent from 'src/module/sw-settings-search/component/sw-settings-search-searchable-content';
import swSettingsSearchExampleModal from 'src/module/sw-settings-search/component/sw-settings-search-example-modal';

Shopware.Component.register('sw-settings-search-searchable-content', swSettingsSearchSearchableContent);
Shopware.Component.register('sw-settings-search-example-modal', swSettingsSearchExampleModal);

async function createWrapper(privileges = []) {
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-settings-search-searchable-content'), {
        localVue,

        propsData: {
            searchConfigId: '',
        },

        provide: {
            repositoryFactory: {
                create() {
                    return Promise.resolve();
                },
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) {
                        return true;
                    }

                    return privileges.includes(identifier);
                },
            },

        },

        stubs: {
            'sw-card': true,
            'sw-container': true,
            'sw-button': {
                template: '<button @click="$emit(\'click\', $event)"><slot></slot></button>',
            },
            'sw-icon': true,
            'sw-tabs': true,
            'sw-tabs-item': true,
            'sw-settings-search-example-modal': await Shopware.Component.build('sw-settings-search-example-modal'),
            'sw-modal': true,
            'router-link': true,
        },
    });
}

describe('module/sw-settings-search/component/sw-settings-search-searchable-content', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('Should be show example modal when the link was clicked', async () => {
        const wrapper = await createWrapper([
            'product_search_config.viewer',
        ]);
        await wrapper.vm.$nextTick();

        const linkElement = wrapper.find('.sw-settings-search__searchable-content-show-example-link');

        await linkElement.trigger('click');
        expect(wrapper.vm.showExampleModal).toBe(true);

        await wrapper.vm.onShowExampleModal();
        const modalElement = wrapper.find('.sw-settings-search-example-modal');
        expect(modalElement.isVisible()).toBe(true);
    });

    it('Should not able to reset to default without editor privilege', async () => {
        const wrapper = await createWrapper([
            'product_search_config.viewer',
        ]);
        await wrapper.vm.$nextTick();

        const resetButton = wrapper.find('.sw-settings-search__searchable-content-reset-button');
        expect(resetButton.attributes().disabled).toBeTruthy();
    });

    it('Should able to reset to default if having editor privilege', async () => {
        const wrapper = await createWrapper([
            'product_search_config.editor',
        ]);
        await wrapper.vm.$nextTick();

        const resetButton = wrapper.find('.sw-settings-search__searchable-content-reset-button');

        wrapper.vm.isEnabledReset = false;
        await wrapper.vm.$nextTick();

        expect(resetButton.isVisible()).toBe(true);
        expect(resetButton.attributes().disabled).toBeFalsy();
    });

    it('should return storefrontEsEnable value', async () => {
        Shopware.Context.app.storefrontEsEnable = true;
        const wrapper = await createWrapper();

        expect(wrapper.vm.storefrontEsEnable).toBeTruthy();
    });
});
