/**
 * @sw-package framework
 */
import 'src/app/mixin/salutation.mixin';
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        {
            template: `
            <div class="sw-mock">
              <slot></slot>
            </div>
        `,
            mixins: [
                Shopware.Mixin.getByName('salutation'),
            ],
            data() {
                return {
                    name: 'sw-mock-field',
                };
            },
        },
        {
            attachTo: document.body,
        },
    );
}

describe('src/app/mixin/salutation.mixin.ts', () => {
    let wrapper;

    beforeEach(async () => {
        jest.spyOn(Shopware.Filter, 'getByName').mockImplementation(() => jest.fn(() => 'Salutation filter result'));

        wrapper = await createWrapper();
        await flushPromises();
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('should compute correct salutationFilter value', () => {
        const result = wrapper.vm.salutationFilter();

        expect(result).toBe('Salutation filter result');
        expect(Shopware.Filter.getByName).toHaveBeenCalledWith('salutation');
    });

    it('should return the correct salutation filter for entity with fallback snippet', () => {
        const result = wrapper.vm.salutation('product', 'myFallbackSnippet');

        expect(result).toBe('Salutation filter result');
        expect(Shopware.Filter.getByName).toHaveBeenCalledWith('salutation');
    });
});
