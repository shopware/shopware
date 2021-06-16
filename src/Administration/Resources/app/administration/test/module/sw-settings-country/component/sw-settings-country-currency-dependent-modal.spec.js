import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-country/component/sw-settings-country-currency-dependent-modal';

function createWrapper(privileges = [], isBasedItem = true) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-settings-country-currency-dependent-modal'), {
        localVue,

        propsData: {
            currencyDependsValue: [{
                enabled: isBasedItem,
                currencyId: '49a246dca3f245e6b83b8b3255c90038',
                amount: 1,
                extensions: []
            }],
            countryId: '',
            userConfig: {},
            userConfigValues: {},
            menuOptions: [{}],
            taxFreeType: '',
            isLoading: ''
        },

        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => {
                        return Promise.resolve([]);
                    }
                })
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            },
            feature: {
                isActive: () => true
            }
        },

        stubs: {
            'sw-modal': {
                template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>'
            },
            'sw-data-grid': {
                props: ['dataSource', 'columns'],
                template: `
                    <div class="sw-data-grid-stub">
                    <template v-for="item in dataSource">
                        <slot name="column-amount" v-bind="{ item }"></slot>
                        <slot name="column-enabled" v-bind="{ item }"></slot>
                        <slot name="actions" v-bind="{ item }"></slot>
                    </template>
                    </div>
                `
            },
            'sw-context-menu-item': true,
            'sw-radio-field': true,
            'sw-number-field': true,
            'sw-button': true
        }
    });
}

describe('module/sw-settings-country/component/sw-settings-country-currency-dependent-modal', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should able to show right column on grid', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        const modalGrid = wrapper.find('.sw-data-grid-stub');
        expect(modalGrid.props().columns).toStrictEqual([{
            inlineEdit: 'string',
            label: '',
            primary: true,
            property: 'currencyId'
        }, {
            inlineEdit: 'string',
            label: 'sw-settings-country.detail.taxFreeFrom',
            primary: true,
            property: 'amount'
        }, {
            inlineEdit: 'string',
            label: 'sw-settings-country.detail.baseCurrency',
            property: 'enabled'
        }]);
    });

    it('should able to show right data on grid', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        const modalGrid = wrapper.find('.sw-data-grid-stub');

        expect(modalGrid.props().dataSource).toStrictEqual([{
            enabled: true,
            currencyId: '49a246dca3f245e6b83b8b3255c90038',
            amount: 1,
            extensions: []
        }]);
    });

    it('should be disabled the context menu delete button', async () => {
        const wrapper = createWrapper(['country.editor']);
        await wrapper.vm.$nextTick();
        const contextMenuDelete = wrapper.find('sw-context-menu-item-stub');

        expect(contextMenuDelete.attributes().disabled).toBeTruthy();
    });

    it('should be enabled the context menu delete button', async () => {
        const wrapper = createWrapper(['country.editor'], false);
        await wrapper.vm.$nextTick();
        const contextMenuDelete = wrapper.find('sw-context-menu-item-stub');

        expect(contextMenuDelete.attributes().disabled).toBeFalsy();
    });

    it('should be enabled the base currency radio button', async () => {
        const wrapper = createWrapper(['country.editor'], false);
        await wrapper.vm.$nextTick();
        const radioButton = wrapper.find('sw-radio-field-stub');
        expect(radioButton.attributes().value).toBeTruthy();
    });

    it('should be change amount for based currency', async () => {
        const wrapper = createWrapper(['country.editor']);
        await wrapper.vm.$nextTick();
        const radioButton = wrapper.find('sw-radio-field-stub');
        expect(radioButton.attributes().disabled).toBeFalsy();
    });

    it('should not be change amount for dependent currency', async () => {
        const wrapper = createWrapper(['country.editor'].false);
        await wrapper.vm.$nextTick();
        const radioButton = wrapper.find('sw-radio-field-stub');
        expect(radioButton.attributes().disabled).toBeTruthy();
    });

    it('should be show buttons at the modal footer', async () => {
        const wrapper = createWrapper(['country.editor']);
        await wrapper.vm.$nextTick();
        const cancelButton = wrapper.find('.sw-settings-country-currency-dependent-modal__cancel-button');
        const saveButton = wrapper.find('.sw-settings-country-currency-dependent-modal__save-button');
        expect(cancelButton.isVisible()).toBeTruthy();
        expect(saveButton.isVisible()).toBeTruthy();
    });
});
