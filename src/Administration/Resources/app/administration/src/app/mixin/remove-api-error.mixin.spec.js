import 'src/app/mixin/remove-api-error.mixin';
import { mount } from '@vue/test-utils';

async function createWrapper(attrs = {}) {
    return mount({
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
        attachTo: document.body,
        attrs,
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
            await wrapper.unmount();
        }

        await flushPromises();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should dispatch removeApiError on value change', async () => {
        await wrapper.unmount();
        wrapper = await createWrapper({
            error: {
                selfLink: 'self.link',
            },
        });
        await flushPromises();

        // add mock for dispatch
        Object.defineProperty(Shopware.State, 'dispatch', {
            value: jest.fn(),
        });

        // change value to trigger watcher
        wrapper.vm.value = 'new-value';

        await flushPromises();

        // expect dispatch to have been called with removeApiError
        expect(Shopware.State.dispatch).toHaveBeenCalledWith('error/removeApiError', {
            expression: 'self.link',
        });
    });
});
