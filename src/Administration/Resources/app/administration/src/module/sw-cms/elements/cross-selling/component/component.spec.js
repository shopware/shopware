/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

const defaultElement = {
    data: {
        product: {
            crossSellings: [],
        },
    },
    config: {
        elMinWidth: {
            value: '300px',
        },
        boxLayout: {
            value: 'standard',
        },
        displayMode: {
            value: 'standard',
        },
    },
};

async function createWrapper(element = defaultElement) {
    return mount(
        await wrapTestComponent('sw-cms-el-cross-selling', {
            sync: true,
        }),
        {
            props: {
                element,
            },
            global: {
                stubs: {
                    'sw-cms-el-product-box': true,
                },
                provide: {
                    cmsService: Shopware.Service('cmsService'),
                },
            },
        },
    );
}

// Mock ResizeObserver which is not available in JSDOM
class ResizeObserverMock {
    constructor(callback) {
        this.callback = callback;
        // Make callback accessible for testing
        this.triggerResize = () => {
            this.callback([{ target: this.observedElement }]);
        };
    }

    observe(element) {
        this.observedElement = element;
        setTimeout(() => this.callback([{ target: element }]), 0);
    }

    unobserve() {}

    disconnect() {}
}

describe('module/sw-cms/elements/cross-selling/component', () => {
    beforeAll(async () => {
        // Add ResizeObserver mock to global
        global.ResizeObserver = ResizeObserverMock;

        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/cross-selling');
    });

    afterEach(() => {
        Shopware.Store.get('cmsPage').resetCmsPageState();
    });

    it('getProductEl applies props to the config object', async () => {
        const wrapper = await createWrapper();
        const product = {
            id: 'foo-bar',
        };

        const elementConfig = wrapper.vm.getProductEl(product);

        expect(elementConfig.data).toMatchObject({
            product,
        });
        expect(elementConfig.config).toMatchObject({
            boxLayout: {
                source: 'static',
                value: 'standard',
            },
            displayMode: {
                source: 'static',
                value: 'standard',
            },
        });
    });

    it('updates sliderBoxLimit based on element width and device view', async () => {
        const wrapper = await createWrapper();

        // Initial value from component creation
        expect(wrapper.vm.sliderBoxLimit).toBe(1);

        Object.defineProperty(wrapper.vm.$refs.productHolder, 'offsetWidth', {
            configurable: true,
            value: 700,
        });

        wrapper.vm.setSliderRowLimit();

        // This should be floor(700 / ((300 - 100) + 32)) = 3
        expect(wrapper.vm.sliderBoxLimit).toBe(3);

        // Test with 1100px width - should fit 4 items
        Object.defineProperty(wrapper.vm.$refs.productHolder, 'offsetWidth', {
            configurable: true,
            value: 1100,
        });

        wrapper.vm.setSliderRowLimit();

        // This should be floor(1100 / ((300 - 100) + 32)) = 4
        expect(wrapper.vm.sliderBoxLimit).toBe(4);

        // Test mobile view - always shows 1 item
        wrapper.vm.cmsPageState.currentCmsDeviceView = 'mobile';
        wrapper.vm.setSliderRowLimit();
        expect(wrapper.vm.sliderBoxLimit).toBe(1);
    });

    it('ResizeObserver triggers setSliderRowLimit when resized', async () => {
        const wrapper = await createWrapper();

        // Spy on the setSliderRowLimit method
        const spy = jest.spyOn(wrapper.vm, 'setSliderRowLimit');

        // Clear previous calls
        spy.mockClear();

        // Trigger the observer callback
        wrapper.vm.resizeObserver.triggerResize();

        // Wait for any async operations
        await new Promise((resolve) => {
            setTimeout(resolve, 20);
        });

        // Verify the method was called
        expect(spy).toHaveBeenCalled();
    });
});
