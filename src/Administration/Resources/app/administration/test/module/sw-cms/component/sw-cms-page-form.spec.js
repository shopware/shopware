import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-cms/component/sw-cms-page-form';
import 'src/app/component/base/sw-card';

function createWrapper() {
    const localVue = createLocalVue();

    localVue.directive('responsive', {});

    return shallowMount(Shopware.Component.build('sw-cms-page-form'), {
        localVue,
        propsData: {
            page: createPageProp()
        },
        stubs: {
            'sw-icon': '<div></div>',
            'sw-card': Shopware.Component.build('sw-card'),
            'sw-cms-el-config-text': '<div class="config-element">Config element</div>'
        },
        mocks: {
            $tc: (value) => value
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
                blocks: []
            },
            {
                blocks: [
                    {
                        slots: [
                            {
                                type: 'text'
                            }
                        ]
                    }
                ]
            }
        ]
    };
}

describe('module/sw-cms/component/sw-cms-page-form', () => {
    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();

        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should have only one empty state \'card\'', () => {
        const wrapper = createWrapper();
        const lengthOfEmptyStates = wrapper.findAll('.sw-cms-page-form__block-card.is--empty').length;


        expect(lengthOfEmptyStates).toBe(1);
    });

    it('should have correct path to snippet', () => {
        const wrapper = createWrapper();
        const textOfEmptyStateBlock = wrapper.find('.sw-cms-page-form__empty-state-text').text();

        expect(textOfEmptyStateBlock).toBe('sw-cms.section.sectionEmptyState');
    });

    it('should have an cms section with a text element', () => {
        const wrapper = createWrapper();
        const configElement = wrapper.find('.config-element');

        expect(configElement.text()).toBe('Config element');
    });
});
