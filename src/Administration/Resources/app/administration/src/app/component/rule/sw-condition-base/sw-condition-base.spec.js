/**
 * @sw-package fundamentals@after-sales
 */
import { mount } from '@vue/test-utils';

const { ShopwareError } = Shopware.Classes;

async function createWrapper(customProps = {}) {
    return mount(await wrapTestComponent('sw-condition-base', { sync: true }), {
        props: {
            condition: {},
            ...customProps,
        },
        global: {
            stubs: {
                'sw-condition-field-errors': await wrapTestComponent('sw-condition-field-errors'),
                'sw-condition-type-select': true,
                'sw-text-field': true,
                'sw-context-button': true,
                'sw-context-menu-item': true,
            },
            provide: {
                conditionDataProviderService: {
                    getPlaceholderData: () => {},
                    getComponentByCondition: () => {},
                },
                availableTypes: {},
                availableGroups: [],
                childAssociationField: {},
            },
        },
    });
}

function seedErrors(conditionId, errors) {
    const errorStore = Shopware.Store.get('error');

    Object.entries(errors).forEach(
        ([
            path,
            error,
        ]) => {
            errorStore.addApiError({
                expression: `rule_condition.${conditionId}.${path}`,
                error,
            });
        },
    );
}

function shopwareError(code = 'INVALID') {
    return new ShopwareError({ detail: 'invalid', code });
}

const condition = {
    id: 'condition-with-errors',
    type: 'cartGoodsCount',
    value: { operator: '=' },
    getEntityName: () => 'rule_condition',
};

describe('src/app/component/rule/sw-condition-base', () => {
    afterEach(() => {
        Shopware.Store.get('error').resetApiErrors();
    });

    it('should have enabled condition type select', async () => {
        const wrapper = await createWrapper();

        const conditionTypeSelect = wrapper.find('sw-condition-type-select-stub');

        expect(conditionTypeSelect.attributes().disabled).toBeUndefined();
    });

    it('should have disabled condition type select', async () => {
        const wrapper = await createWrapper({
            disabled: true,
        });

        const conditionTypeSelect = wrapper.find('sw-condition-type-select-stub');

        expect(conditionTypeSelect.attributes().disabled).toBe('true');
    });

    it('should have enabled context button', async () => {
        const wrapper = await createWrapper();

        const contextButton = wrapper.find('sw-context-button-stub');

        expect(contextButton.attributes().disabled).toBeUndefined();
    });

    it('should have disabled context button', async () => {
        const wrapper = await createWrapper({
            disabled: true,
        });

        const contextButton = wrapper.find('sw-context-button-stub');

        expect(contextButton.attributes().disabled).toBe('true');
    });

    it('should have enabled context menu item', async () => {
        const wrapper = await createWrapper();

        const contextMenuItems = wrapper.findAll('sw-context-menu-item-stub');
        contextMenuItems.forEach((contextMenuItem) => {
            expect(contextMenuItem.attributes().disabled).toBeUndefined();
        });
    });

    it('should have disabled context menu item', async () => {
        const wrapper = await createWrapper({
            disabled: true,
        });

        const contextMenuItems = wrapper.findAll('sw-context-menu-item-stub');
        contextMenuItems.forEach((contextMenuItem) => {
            expect(contextMenuItem.attributes().disabled).toBe('true');
        });
    });

    describe('error display', () => {
        it('does not render the field-errors component when the store has no errors', async () => {
            const wrapper = await createWrapper({ condition });
            await flushPromises();

            expect(wrapper.find('.sw-condition-field-errors-stub').exists()).toBe(false);
            expect(wrapper.find('.sw-condition__container').classes()).not.toContain('has--error');
        });

        it('renders the field-errors component when a value error is present', async () => {
            seedErrors(condition.id, { 'value.count': shopwareError() });

            const wrapper = await createWrapper({ condition });
            await flushPromises();

            expect(wrapper.find('.sw-condition-field-errors').exists()).toBe(true);
            expect(wrapper.find('.sw-condition__container').classes()).toContain('has--error');
        });

        it('passes the type error and field errors to the field-errors component', async () => {
            seedErrors(condition.id, {
                type: shopwareError('RULE_TYPE_REQUIRED'),
                'value.count': shopwareError(),
                'value.operator': shopwareError(),
            });

            const wrapper = await createWrapper({ condition });
            await flushPromises();

            const errorField = wrapper.find('.sw-condition-field-errors__message').text();

            expect(errorField).toContain('type');
            expect(errorField).toContain('count');
            expect(errorField).toContain('operator');
        });

        it('skips nested non-leaf value branches that are not ShopwareError instances', async () => {
            seedErrors(condition.id, {
                'value.count': shopwareError(),
                'value.timezone.nested': shopwareError(),
            });

            const wrapper = await createWrapper({ condition });
            await flushPromises();

            const errorField = wrapper.find('.sw-condition-field-errors__message').text();

            expect(errorField).toContain('count');
        });
    });
});
