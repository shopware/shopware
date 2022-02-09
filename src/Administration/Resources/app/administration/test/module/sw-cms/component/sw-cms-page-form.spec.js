import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-cms/component/sw-cms-page-form';
import 'src/app/component/base/sw-card';
import 'src/app/component/utils/sw-ignore-class';

function createWrapper() {
    const localVue = createLocalVue();
    localVue.directive('responsive', {});

    return shallowMount(Shopware.Component.build('sw-cms-page-form'), {
        localVue,
        propsData: {
            page: createPageProp()
        },
        stubs: {
            'sw-icon': {
                template: '<div></div>'
            },
            'sw-card': Shopware.Component.build('sw-card'),
            'sw-cms-el-config-text': {
                template: '<div class="config-element">Config element</div>'
            },
            'sw-extension-component-section': true,
            'sw-ignore-class': Shopware.Component.build('sw-ignore-class'),
        },
        provide: {
            cmsService: {
                getCmsBlockRegistry: () => {
                    return {};
                },
                getCmsElementRegistry: () => {
                    return {
                        text: {
                            configComponent: 'sw-cms-el-config-text'
                        }
                    };
                }
            }
        }
    });
}

function createPageProp() {
    // providing only bare minimum

    return {
        sections: [
            {
                blocks: [
                    {
                        name: 'BLOCK NAME',
                        slots: [
                            {
                                type: 'text'
                            }
                        ]
                    }
                ]
            },
            {
                blocks: []
            },
        ]
    };
}

describe('module/sw-cms/component/sw-cms-page-form', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have only one empty state \'card\'', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        const lengthOfEmptyStates = wrapper.findAll('.sw-cms-page-form__block-card.is--empty').length;

        expect(lengthOfEmptyStates).toBe(1);
    });

    it('should have correct path to snippet', async () => {
        const wrapper = createWrapper();
        const textOfEmptyStateBlock = wrapper.find('.sw-cms-page-form__empty-state-text').text();

        expect(textOfEmptyStateBlock).toBe('sw-cms.section.sectionEmptyState');
    });

    it('should have an cms section with a text element', async () => {
        const wrapper = createWrapper();
        const configElement = wrapper.find('.config-element');

        expect(configElement.text()).toBe('Config element');
    });

    it('display the block name', async () => {
        const wrapper = createWrapper();
        const blockNameText = wrapper.find('.sw-card__title').text();

        expect(blockNameText).toBe('BLOCK NAME');
    });
});
