/**
 * @package admin
 */

import { mount } from '@vue/test-utils';
import 'src/app/component/form/select/base/sw-select-result';

describe('src/app/component/form/select/base/sw-select-result', () => {
    let wrapper;
    let swSelectResult;

    async function createWrapper(innerTemplate = '') {
        const Parent = {
            components: {
                swSelectResult,
            },
            name: 'Parent',
            data() {
                return {
                    showSwSelectResult: true,
                };
            },
            template: `
            <div class="parent">
            <sw-select-result
                v-if="showSwSelectResult"
                :index="0"
                :item="{
                        name: 'hgfhg',
                        createdAt: '2020-08-07T13:03:59.581+00:00',
                        updatedAt: null,
                        apiAlias: null,
                        id: '084310ac700b4f6a8a008bb7843399e2',
                        products: [],
                        media: [],
                        categories: [],
                        customers: [],
                        orders: [],
                        shippingMethods: [],
                        newsletterRecipients: []
                    }"
            >${innerTemplate}</sw-select-result>
            </div>`,
        };

        const GrandParent = {
            template: '<div><Parent></Parent></div>',
            components: { Parent },
        };

        const GrandParentAsync = {
            template: '<div><GrandParent></GrandParent></div>',
            components: { GrandParent },
        };

        const GrandParentAsyncTwo = {
            template: '<div><GrandParentAsync></GrandParentAsync></div>',
            components: { GrandParentAsync },
        };

        const grandGrandParent = {
            template: '<div><GrandParentAsyncTwo></GrandParentAsyncTwo></div>',
            components: { GrandParentAsyncTwo },
            methods: {
                emitSelectItemByKeyboard() {
                    this.$emit('item-select-by-keyboard', [0]);
                },
            },
        };

        return mount(grandGrandParent, {
            provide: {
                repositoryFactory: {
                    create: () => ({ search: () => Promise.resolve('bar') }),
                },
                setActiveItemIndex: () => {},
            },
            attachTo: document.body,
        });
    }

    beforeAll(async () => {
        swSelectResult = await Shopware.Component.build('sw-select-result');
        swSelectResult.methods.checkIfSelected = jest.fn();
    });

    beforeEach(async () => {
        wrapper = await createWrapper();

        await flushPromises();
    });

    afterEach(() => {
        wrapper.unmount();
    });

    it('should react on $parent.$parent event', async () => {
        const swSelectResultWrapper = wrapper.findComponent('.sw-select-result').vm;
        expect(swSelectResult.methods.checkIfSelected).toHaveBeenCalledTimes(0);

        wrapper.vm.emitSelectItemByKeyboard();
        await swSelectResultWrapper.$nextTick();
        await flushPromises();

        expect(swSelectResult.methods.checkIfSelected).toHaveBeenCalledTimes(1);
    });

    it('should show description depending on slot', async () => {
        wrapper = await createWrapper();

        expect(wrapper.find('.sw-select-result__result-item-description').exists()).toBeFalsy();

        wrapper = await createWrapper(`
            <template #description>
                foobar
            </template>
        `);

        expect(wrapper.find('.sw-select-result__result-item-description').exists()).toBeTruthy();
        expect(wrapper.find('.sw-select-result__result-item-description').text()).toContain('foobar');
    });
});
