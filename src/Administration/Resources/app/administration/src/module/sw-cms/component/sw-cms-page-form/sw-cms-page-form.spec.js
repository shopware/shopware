/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';

let resizeObserverList = [];

global.ResizeObserver = class ResizeObserver {
    constructor(callback) {
        this.observerCallback = callback;
        this.observerList = [];

        resizeObserverList.push(this);
    }

    observe(el) {
        this.observerList.push(el);
    }

    unobserve() {
        // do nothing
    }

    disconnect() {
        // do nothing
    }

    _execute() {
        this.observerCallback(this.observerList);
    }
};

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
    return mount(
        await wrapTestComponent('sw-cms-page-form', {
            sync: true,
        }),
        {
            props: {
                page: defaultPage,
            },
            global: {
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
                        props: [
                            'element',
                            'elementData',
                        ],
                    },
                    'sw-extension-component-section': true,
                    'sw-alert': true,
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
            },
        },
    );
}

describe('module/sw-cms/component/sw-cms-page-form', () => {
    beforeEach(() => {
        resizeObserverList = [];
    });

    it("should have only one empty state 'card'", async () => {
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
        const blockNameText = wrapper.findComponent('.sw-cms-page-form__block-card').props('title');

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
