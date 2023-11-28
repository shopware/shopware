import { shallowMount, createLocalVue } from '@vue/test-utils';

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

async function createWrapper(responsiveBindings = {}) {
    const localVue = createLocalVue();

    return shallowMount({
        name: 'placeholder-component',
        template: `
<div class="placeholder-component" v-responsive="responsiveBindings">
    <h1>Placeholder component</h1>
</div>
`,
        props: {
            responsiveBindings: {
                required: true,
            },
        },
    }, {
        localVue,
        stubs: {},
        mocks: {},
        propsData: {
            responsiveBindings,
        },
        attachTo: document.body,
    });
}

describe('src/app/directive/responsive.directive.ts', () => {
    let wrapper;

    beforeEach(async () => {
        resizeObserverList = [];
        wrapper = await createWrapper({
            'is--compact': el => el.width <= 1620,
            timeout: 200,
        });

        await flushPromises();
    });

    afterEach(async () => {
        if (wrapper) {
            await wrapper.destroy();
        }

        await flushPromises();
    });

    it('should execute all observer and show the "is--compact" css class', () => {
        resizeObserverList.at(0).observerList.forEach(el => {
            el.contentRect = {
                width: 500,
            };
        });
        resizeObserverList.at(0)._execute();

        expect(wrapper.classes()).toContain('is--compact');
    });

    it('should execute all observer and not show the "is--compact" css class', () => {
        resizeObserverList.at(0).observerList.forEach(el => {
            el.contentRect = {
                width: 1920,
            };
        });
        resizeObserverList.at(0)._execute();

        expect(wrapper.classes()).not.toContain('is--compact');
    });

    it('should execute all observer without error even if no binding was provided', async () => {
        resizeObserverList = [];
        wrapper = await createWrapper(null);
        await flushPromises();

        resizeObserverList.at(0).observerList.forEach(el => {
            el.contentRect = {
                width: 500,
            };
        });

        expect(() => resizeObserverList.at(0)._execute()).not.toThrow();
    });
});
