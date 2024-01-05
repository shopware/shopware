/**
 * @package buyers-experience
 */
import { shallowMount, createLocalVue } from '@vue/test-utils';
import swCmsPageForm from 'src/module/sw-cms/component/sw-cms-page-form';

Shopware.Component.register('sw-cms-page-form', swCmsPageForm);

const defaultPage = {
    sections: [
        {
            blocks: [
                {
                    name: 'BLOCK NAME',
                    slots: [
                        {
                            type: 'text',
                        },
                    ],
                },
            ],
        },
        {
            blocks: [],
        },
    ],
};

async function createWrapper() {
    const localVue = createLocalVue();
    localVue.directive('responsive', {});

    return shallowMount(await Shopware.Component.build('sw-cms-page-form'), {
        localVue,
        propsData: {
            page: defaultPage,
        },
        stubs: {
            'sw-icon': {
                template: '<div></div>',
            },
            'sw-card': {
                template: '<div class="sw-card"><slot /><slot name="header-right"></slot></div>',
                props: ['title'],
            },
            'sw-cms-el-config-text': {
                template: '<div class="sw-cms-el-config-text">Config element</div>',
                props: ['element', 'elementData'],
            },
            'sw-extension-component-section': true,
        },
        provide: {
            cmsService: {
                getCmsBlockRegistry: () => {
                    return {};
                },
                getCmsElementRegistry: () => {
                    return {
                        text: {
                            configComponent: 'sw-cms-el-config-text',
                        },
                    };
                },
            },
        },
    });
}

describe('module/sw-cms/component/sw-cms-page-form', () => {
    it('should have only one empty state \'card\'', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        const lengthOfEmptyStates = wrapper.findAll('.sw-cms-page-form__block-card.is--empty').length;

        expect(lengthOfEmptyStates).toBe(1);
    });

    it('should have correct path to snippet', async () => {
        const wrapper = await createWrapper();
        const textOfEmptyStateBlock = wrapper.find('.sw-cms-page-form__empty-state-text').text();

        expect(textOfEmptyStateBlock).toBe('sw-cms.section.sectionEmptyState');
    });

    it('should have an cms section with a text element', async () => {
        const wrapper = await createWrapper();
        const configElement = wrapper.getComponent('.sw-cms-el-config-text');

        expect(configElement.text()).toBe('Config element');
        expect(configElement.props()).toEqual({
            element: {
                type: 'text',
            },
            elementData: {
                configComponent: 'sw-cms-el-config-text',
            },
        });
    });

    it('display the block name', async () => {
        const wrapper = await createWrapper();
        const blockNameText = wrapper.find('.sw-cms-page-form__block-card').props('title');

        expect(blockNameText).toBe('BLOCK NAME');
    });

    it('display the device active in viewport', async () => {
        const wrapper = await createWrapper();
        const formDeviceActions = wrapper.find('.sw-cms-page-form__device-actions');
        const blockFormDeviceActions = wrapper.find('.sw-cms-page-form__block-device-actions');

        expect(formDeviceActions.exists()).toBeTruthy();
        expect(blockFormDeviceActions.exists()).toBeTruthy();
    });
});
