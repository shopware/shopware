import { mount } from '@vue/test-utils';
import 'src/app/component/form/select/base/sw-select-result/';


function createWrapper(innerTemplate = '') {
    const swSelectResult = Shopware.Component.build('sw-select-result');
    const Parent = {
        components: {
            swSelectResult
        },
        name: 'Parent',
        data() {
            return {
                showSwSelectResult: true
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
            </div>`
    };

    const grandParent = {
        template: '<div><Parent></Parent></div>',
        components: {
            Parent
        },
        methods: {
            emitSelectItemByKeyboard() {
                this.$emit('item-select-by-keyboard', [0]);
            }
        }
    };

    return mount(grandParent, {
        provide: {
            repositoryFactory: {
                create: () => ({ search: () => Promise.resolve('bar') })
            },
            setActiveItemIndex: () => {
            }
        }
    });
}


describe('src/app/component/form/select/base/sw-select-result/', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });
    afterEach(() => {
        wrapper.destroy();
    });


    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should react on $parent.$parent event', async () => {
        const swSelectResult = wrapper.find('.sw-select-result').vm;
        swSelectResult.checkIfSelected = jest.fn();
        const checkIfSelectedSpy = swSelectResult.checkIfSelected;

        expect(checkIfSelectedSpy).toBeCalledTimes(0);
        wrapper.vm.emitSelectItemByKeyboard();
        wrapper.vm.$nextTick().then(() => expect(checkIfSelectedSpy).toBeCalledTimes(1));
    });

    it('should remove the event listener', async () => {
        await wrapper.find('.parent').setData({
            showSwSelectResult: false
        });

        // $on and $off methods get each called twice in the lifecyclehooks
        // because we are using two listeners
        const onSpy = jest.spyOn(wrapper.vm, '$on');
        const offSpy = jest.spyOn(wrapper.vm, '$off');

        expect(onSpy).toHaveBeenCalledTimes(0);
        expect(onSpy).toHaveBeenCalledTimes(0);

        await wrapper.find('.parent').setData({
            showSwSelectResult: true
        });

        expect(onSpy).toHaveBeenCalledTimes(2);
        expect(offSpy).toHaveBeenCalledTimes(0);

        await wrapper.find('.parent').setData({
            showSwSelectResult: false
        });

        expect(onSpy).toHaveBeenCalledTimes(2);
        expect(offSpy).toHaveBeenCalledTimes(2);
    });

    it('should show description depending on slot (with FEATURE_NEXT_16800)', async () => {
        /**
         * Test without feature flag and re-create wrapper with active feature flag.
         * Remove the next 3 lines with FEATURE_NEXT_16800
         */
        expect(wrapper.find('.sw-select-result__result-item-description').exists()).toBeFalsy();
        global.activeFeatureFlags = ['FEATURE_NEXT_16800'];
        wrapper = createWrapper();

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
