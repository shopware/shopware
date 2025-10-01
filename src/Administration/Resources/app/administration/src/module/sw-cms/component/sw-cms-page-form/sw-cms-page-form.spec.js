/**
 * @sw-package discovery
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
                    'sw-cms-el-config-text': {
                        template: '<div class="sw-cms-el-config-text">Config element</div>',
                        props: [
                            'element',
                            'elementData',
                        ],
                    },
                    'sw-inheritance-switch': true,
                    'sw-extension-component-section': true,
                    'mt-card': true,
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
        const blockNameText = wrapper.findByText('div', 'BLOCK NAME');

        expect(blockNameText.exists()).toBe(true);
    });

    it('display the device active in viewport', async () => {
        const wrapper = await createWrapper();
        const formDeviceActions = wrapper.find('.sw-cms-page-form__device-actions');
        const blockFormDeviceActions = wrapper.find('.sw-cms-page-form__block-device-actions');

        await flushPromises();
        await wrapper.vm.$nextTick();

        expect(formDeviceActions.exists()).toBeTruthy();
        expect(blockFormDeviceActions.exists()).toBeTruthy();
    });

    it('disables element config when block is inherited and shows inheritance switch', async () => {
        const wrapper = await mount(
            await wrapTestComponent('sw-cms-page-form', { sync: true }),
            {
                props: {
                    page: defaultPage,
                    entityConfig: {},
                },
                global: {
                    stubs: {
                        'sw-cms-el-config-text': {
                            template: '<div class="sw-cms-el-config-text">Config element</div>',
                            props: ['element', 'elementData'],
                        },
                        'sw-inheritance-switch': true,
                        'sw-extension-component-section': true,
                        'mt-card': true,
                    },
                    provide: {
                        cmsService: {
                            getCmsBlockRegistry: () => ({}),
                            getCmsElementRegistry: () => ({
                                text: { configComponent: 'sw-cms-el-config-text' },
                            }),
                        },
                    },
                },
            },
        );

        const elementConfig = wrapper.find('.sw-cms-page-form__element-config');
        expect(elementConfig.exists()).toBeTruthy();
        // inherited => disabled overlay is applied
        expect(elementConfig.attributes('disabled')).toBe('true');

        const inheritanceSwitch = wrapper.find('sw-inheritance-switch-stub');
        expect(inheritanceSwitch.exists()).toBeTruthy();
    });

    it('applies entity-config overrides to slot element passed to config component', async () => {
        const pageWithIds = {
            sections: [{
                blocks: [{
                    name: 'BLOCK NAME',
                    slots: [{ id: 'slot1', type: 'text', config: { content: { value: 'original' } } }],
                }],
            }],
        };

        const wrapper = await mount(
            await wrapTestComponent('sw-cms-page-form', { sync: true }),
            {
                props: {
                    page: pageWithIds,
                    entityConfig: {
                        slot1: { content: { value: 'overridden', source: 'static' } },
                    },
                },
                global: {
                    stubs: {
                        'sw-cms-el-config-text': {
                            template: '<div class="sw-cms-el-config-text">Config element</div>',
                            props: ['element', 'elementData'],
                        },
                        'sw-inheritance-switch': true,
                        'sw-extension-component-section': true,
                        'mt-card': true,
                    },
                    provide: {
                        cmsService: {
                            getCmsBlockRegistry: () => ({}),
                            getCmsElementRegistry: () => ({
                                text: { configComponent: 'sw-cms-el-config-text' },
                            }),
                        },
                    },
                },
            },
        );

        const configEl = wrapper.getComponent('.sw-cms-el-config-text');
        const passedElement = configEl.props('element');

        expect(passedElement).toEqual({
            id: 'slot1',
            type: 'text',
            config: { content: { value: 'overridden', source: 'static' } },
        });
    });
});
