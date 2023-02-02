import { shallowMount } from '@vue/test-utils';
import swTaxRuleCard from 'src/module/sw-settings-tax/component/sw-tax-rule-card';

Shopware.Component.register('sw-tax-rule-card', swTaxRuleCard);

/**
 * @package customer-order
 */
async function createWrapper(privileges = []) {
    return shallowMount(await Shopware.Component.build('sw-tax-rule-card'), {
        propsData: {
            tax: {
                id: 'id',
                taxId: 'taxId',
                taxRate: 'taxRate'
            },
            isLoading: false,
            disabled: false
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => {
                        return Promise.resolve([
                            {
                                id: 'id',
                                taxId: 'taxId',
                                taxRate: 'taxRate'
                            }
                        ]);
                    },

                    delete: () => {
                        return Promise.resolve();
                    }
                })
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) {
                        return true;
                    }

                    return privileges.includes(identifier);
                }
            }
        },
        stubs: {
            'sw-card': {
                template: `
                    <div class="sw-card">
                        <slot name="title"></slot>
                        <slot name="tabs"></slot>
                        <slot name="toolbar"></slot>
                        <slot name="default"></slot>
                        <slot name="grid"></slot>
                        <slot name="footer"></slot>
                        <slot></slot>
                    </div>
                `
            },
            'sw-card-section': {
                template: `
                    <div class="sw-card-section">
                        <slot></slot>
                    </div>
                `
            },
            'sw-card-filter': {
                template: `
                    <div class="sw-card-filter">
                        <slot name="filter"></slot>
                    </div>
                `
            },
            'sw-data-grid': {
                props: ['dataSource'],
                template: `
                    <div class="sw-data-grid">
                        <template v-for="item in dataSource">
                            <slot name="actions" v-bind="{ item }"></slot>
                        </template>
                    </div>
                `
            },
            'sw-context-menu-item': true,
            'sw-button': true
        }
    });
}

describe('module/sw-settings-tax/component/sw-tax-rule-card', () => {
    const init = async (privileges, taxRules) => {
        const wrapper = await createWrapper(privileges);
        await wrapper.vm.$nextTick();

        await wrapper.setData({ taxRules });
        await wrapper.vm.$nextTick();

        return { wrapper };
    };

    describe('when tax.editor privilege is provided and have tax rules', () => {
        let wrapper;

        beforeEach(async () => {
            wrapper = await (await init('tax.editor', [{}])).wrapper;
        });

        afterEach(() => {
            wrapper.destroy();
        });

        it('should be a Vue.JS component', async () => {
            expect(wrapper.vm).toBeTruthy();
        });

        it('should be able to add a new country from data grid', async () => {
            const addButton = wrapper.find('.sw-tax-rule-grid-button');

            expect(addButton.attributes().disabled).toBeFalsy();
        });

        it('should be able to edit a country from data grid', async () => {
            const editMenuItem = wrapper.find('.sw-tax-list__edit-action');

            expect(editMenuItem.attributes().disabled).toBeFalsy();
        });

        it('should be able to delete a country from data grid', async () => {
            const deleteMenuItem = wrapper.find('.sw-tax-list__delete-action');

            expect(deleteMenuItem.attributes().disabled).toBeFalsy();
        });
    });

    describe('when tax.editor privilege is not provided and have tax rules', () => {
        let wrapper;

        beforeEach(async () => {
            wrapper = await (await init('', [{}])).wrapper;
        });

        afterEach(() => {
            wrapper.destroy();
        });

        it('should be a Vue.JS component', async () => {
            expect(wrapper.vm).toBeTruthy();
        });

        it('should not be able to add a new country from data grid', async () => {
            const addButton = wrapper.find('.sw-tax-rule-grid-button');

            expect(addButton.attributes().disabled).toBeTruthy();
        });

        it('should not be able to edit a country from data grid', async () => {
            const editMenuItem = wrapper.find('.sw-tax-list__edit-action');

            expect(editMenuItem.attributes().disabled).toBeTruthy();
        });

        it('should not be able to delete a country from data grid', async () => {
            const deleteMenuItem = wrapper.find('.sw-tax-list__delete-action');

            expect(deleteMenuItem.attributes().disabled).toBeTruthy();
        });
    });

    describe('when tax.editor privilege is provided and have no tax rules', () => {
        let wrapper;

        beforeEach(async () => {
            wrapper = await (await init('tax.editor', [])).wrapper;
        });

        afterEach(() => {
            wrapper.destroy();
        });

        it('should be a Vue.JS component', async () => {
            expect(wrapper.vm).toBeTruthy();
        });

        it('should be able to add a new country from empty card', async () => {
            const addButton = wrapper.find('.sw-settings-tax-rule-card__empty-state--button');

            expect(addButton.attributes().disabled).toBeFalsy();
        });
    });

    describe('when tax.editor privilege is not provided and have no tax rules', () => {
        let wrapper;

        beforeEach(async () => {
            wrapper = await (await init('', [])).wrapper;
        });

        afterEach(() => {
            wrapper.destroy();
        });

        it('should be a Vue.JS component', async () => {
            expect(wrapper.vm).toBeTruthy();
        });

        it('should not be able to add a new country from empty card', async () => {
            const addButton = wrapper.find('.sw-settings-tax-rule-card__empty-state--button');

            expect(addButton.attributes().disabled).toBeTruthy();
        });
    });
});
