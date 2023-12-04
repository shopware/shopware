import 'src/app/mixin/discard-detail-page-changes.mixin';
import { shallowMount } from '@vue/test-utils_v2';

async function createWrapper(...entityNames) {
    return shallowMount({
        template: `
            <div class="sw-mock">
              <slot></slot>
            </div>
        `,
        mixins: [
            Shopware.Mixin.getByName('discard-detail-page-changes')(...entityNames),
        ],
        data() {
            return {
                product: {
                    discardChanges: jest.fn(() => true),
                },
                category: {
                    discardChanges: jest.fn(() => true),
                },
                property: {
                    discardChanges: jest.fn(() => true),
                },
            };
        },
    }, {
        stubs: {},
        mocks: {
            $route: {
                params: {
                    id: '1',
                },
            },
        },
        attachTo: document.body,
    });
}

describe('src/app/mixin/discard-detail-page-changes.mixin.ts', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper('product');

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

    it('should call the entity discardChanges function on route params id change', async () => {
        expect(wrapper.vm.product.discardChanges).not.toHaveBeenCalled();

        // simulate route id change
        wrapper.vm.$route.params.id = '2';
        await flushPromises();

        expect(wrapper.vm.product.discardChanges).toHaveBeenCalledWith();
    });

    it('should call the entity discardChanges function on every given name', async () => {
        await wrapper.destroy();
        wrapper = await createWrapper('product', ['category', 'property']);

        expect(wrapper.vm.product.discardChanges).not.toHaveBeenCalled();
        expect(wrapper.vm.category.discardChanges).not.toHaveBeenCalled();
        expect(wrapper.vm.property.discardChanges).not.toHaveBeenCalled();

        // simulate route id change
        wrapper.vm.$route.params.id = '2';
        await flushPromises();

        expect(wrapper.vm.product.discardChanges).toHaveBeenCalledWith();
        expect(wrapper.vm.category.discardChanges).toHaveBeenCalledWith();
        expect(wrapper.vm.property.discardChanges).toHaveBeenCalledWith();
    });

    it('should throw an error if no entity name is given', async () => {
        await wrapper.destroy();

        await expect(createWrapper())
            .rejects
            .toThrow('discard-detail-page-changes.mixin - You need to provide the entity names');
    });

    it('should log a warning when not entity with the name has a discard method was found', async () => {
        await wrapper.destroy();

        wrapper = await createWrapper('manufacturer');

        jest.spyOn(Shopware.Utils.debug, 'warn').mockImplementationOnce(() => {});

        // simulate route id change
        wrapper.vm.$route.params.id = '2';
        await flushPromises();

        expect(Shopware.Utils.debug.warn).toHaveBeenCalledWith(
            'Discard-detail-page-changes Mixin',
            'Could not discard changes for entity with name "manufacturer".',
        );
    });
});
