import { mount } from '@vue/test-utils';
import getTreeItems from 'src/app/component/tree/sw-tree/fixtures/treeItems';

/**
 * @package services-settings
 */

const testAssociationName = 'testAssociation';

const testAssociations = [
    {
        id: '1',
        name: 'Test association',
    },
];

const testData = {
    [testAssociationName]: testAssociations,
};

const testCases = [
    {
        name: 'association in data is filled',
        data: testData,
        extensions: {},
        disabled: true,
    },
    {
        name: 'association in extension is filled',
        data: {},
        extensions: testData,
        disabled: true,
    },
    {
        name: 'association in data & extension is filled',
        data: testData,
        extensions: testData,
        disabled: true,
    },
    {
        name: 'association in data & extension is empty',
        data: {},
        extensions: {},
        disabled: false,
    },
];

const defaultProps = {
    item: {
        ...getTreeItems()[0],
        active: false,
        data: {
            extensions: {},
        },
    },
    association: testAssociationName,
    hideActions: false,
};

async function createWrapper(props = defaultProps) {
    return mount(await wrapTestComponent('sw-settings-rule-tree-item', { sync: true }), {
        props,
        global: {
            stubs: {
                'sw-tree-item': await wrapTestComponent('sw-tree-item'),
                'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
            },
            mocks: {
                $el: {
                    querySelector: jest.fn(),
                },
            },
        },
    });
}

describe('src/module/sw-settings-rule/view/sw-settings-rule-tree-item', () => {
    it.each([
        { expected: true },
        { expected: false },
    ])('should hide actions: $expected', async ({ expected }) => {
        const wrapper = await createWrapper({
            ...defaultProps,
            hideActions: expected,
        });
        await flushPromises();

        expect(wrapper.find('.sw-tree-item__actions').exists()).toBe(!expected);
    });

    it.each(testCases)('should check association: $name', async ({ data, extensions, disabled }) => {
        const wrapper = await createWrapper({
            ...defaultProps,
            item: {
                ...defaultProps.item,
                data: {
                    ...data,
                    extensions,
                },
            },
        });
        await flushPromises();

        expect(wrapper.find('.sw-tree-item__selection input').attributes('disabled')).toBe(disabled ? '' : undefined);
    });
});
