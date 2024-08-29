/**
 * @package admin
 * @group disabledCompat
 */
import 'src/app/mixin/discard-detail-page-changes.mixin';
import { mount, config } from '@vue/test-utils';
import { createRouter, createWebHashHistory } from 'vue-router';

async function createWrapper(...entityNames) {
    delete config.global.mocks.$route;
    delete config.global.mocks.$router;

    const router = createRouter({
        history: createWebHashHistory(),
        routes: [
            {
                name: 'sw.jest.index',
                path: '/jest/:id',
                component: {
                    template: '<div></div>',
                },
            },
        ],
    });

    await router.push({ name: 'sw.jest.index', params: { id: '1' } });

    return mount({
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
        global: {
            plugins: [
                router,
            ],
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

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should call the entity discardChanges function on route params id change', async () => {
        expect(wrapper.vm.product.discardChanges).not.toHaveBeenCalled();

        // simulate route id change
        await wrapper.vm.$router.push({ name: 'sw.jest.index', params: { id: '2' } });

        expect(wrapper.vm.product.discardChanges).toHaveBeenCalledWith();
    });

    it('should call the entity discardChanges function on every given name', async () => {
        await wrapper.unmount();
        wrapper = await createWrapper('product', ['category', 'property']);

        expect(wrapper.vm.product.discardChanges).not.toHaveBeenCalled();
        expect(wrapper.vm.category.discardChanges).not.toHaveBeenCalled();
        expect(wrapper.vm.property.discardChanges).not.toHaveBeenCalled();

        // simulate route id change
        await wrapper.vm.$router.push({ name: 'sw.jest.index', params: { id: '2' } });

        expect(wrapper.vm.product.discardChanges).toHaveBeenCalledWith();
        expect(wrapper.vm.category.discardChanges).toHaveBeenCalledWith();
        expect(wrapper.vm.property.discardChanges).toHaveBeenCalledWith();
    });

    it('should throw an error if no entity name is given', async () => {
        await wrapper.unmount();

        await expect(createWrapper())
            .rejects
            .toThrow('discard-detail-page-changes.mixin - You need to provide the entity names');
    });

    it('should log a warning when not entity with the name has a discard method was found', async () => {
        await wrapper.unmount();

        wrapper = await createWrapper('manufacturer');

        jest.spyOn(Shopware.Utils.debug, 'warn').mockImplementationOnce(() => {});

        // simulate route id change
        await wrapper.vm.$router.push({ name: 'sw.jest.index', params: { id: '2' } });

        expect(Shopware.Utils.debug.warn).toHaveBeenCalledWith(
            'Discard-detail-page-changes Mixin',
            'Could not discard changes for entity with name "manufacturer".',
        );
    });
});
