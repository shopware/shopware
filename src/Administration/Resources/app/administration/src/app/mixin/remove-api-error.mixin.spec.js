import 'src/app/mixin/remove-api-error.mixin';
import { shallowMount } from '@vue/test-utils';

async function createWrapper() {
    return shallowMount({
        template: `
            <div class="sw-mock">
              <slot></slot>
            </div>
        `,
        mixins: [
            Shopware.Mixin.getByName('remove-api-error'),
        ],
        data() {
            return {
                name: 'sw-mock-field',
                value: 'initial-value',
            };
        },
    }, {
        stubs: {},
        mocks: {},
        attachTo: document.body,
    });
}

describe('src/app/mixin/remove-api-error.mixin.ts', () => {
    let wrapper;
    let originalDispatch;

    beforeEach(async () => {
        if (originalDispatch) {
            Object.defineProperty(Shopware.State, 'dispatch', {
                value: originalDispatch,
            });
        } else {
            originalDispatch = Shopware.State.dispatch;
        }

        wrapper = await createWrapper();

        await flushPromises();
    });

    afterEach(async () => {
        if (wrapper) {
            await wrapper.destroy();
        }

        await flushPromises();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should add a watcher for value', () => {
        const valueWatcher = wrapper.vm._watchers.find(w => w.expression === 'value');

        expect(valueWatcher).toBeDefined();
    });

    it('should dispatch removeApiError on value change', async () => {
        // add mock for dispatch
        Object.defineProperty(Shopware.State, 'dispatch', {
            value: jest.fn(),
        });

        // mock error attrs value
        wrapper.vm.$attrs.error = {
            selfLink: 'self.link',
        };

        // change value to trigger watcher
        wrapper.vm.value = 'new-value';

        await flushPromises();

        // expect dispatch to have been called with removeApiError
        expect(Shopware.State.dispatch).toHaveBeenCalledWith('error/removeApiError', {
            expression: 'self.link',
        });
    });
});
