/**
 * @sw-package framework
 */
import 'src/app/mixin/remove-api-error.mixin';
import { mount } from '@vue/test-utils';

async function createWrapper(attrs = {}) {
    return mount(
        {
            template: `
            <div class="sw-mock">
              <slot></slot>
            </div>
        `,
            mixins: [Shopware.Mixin.getByName('remove-api-error')],
            data() {
                return {
                    name: 'sw-mock-field',
                    value: 'initial-value',
                };
            },
        },
        {
            attachTo: document.body,
            attrs,
        },
    );
}

describe('src/app/mixin/remove-api-error.mixin.ts', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();

        await flushPromises();
    });

    afterEach(async () => {
        if (wrapper) {
            await wrapper.unmount();
        }

        await flushPromises();
    });

    it('should call  removeApiError on value change', async () => {
        await wrapper.unmount();
        wrapper = await createWrapper({
            error: {
                selfLink: 'self.link',
            },
        });
        await flushPromises();

        // add mock for removeApiError
        const errorStore = Shopware.Store.get('error');
        jest.spyOn(errorStore, 'removeApiError');

        // change value to trigger watcher
        wrapper.vm.value = 'new-value';

        await flushPromises();

        expect(Shopware.Store.get('error').removeApiError).toHaveBeenCalledWith('self.link');
    });
});
