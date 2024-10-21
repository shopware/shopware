/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

const seoDomainPrefix = '124c71d524604ccbad6042edce3ac799';

const linkDataProvider = [
    {
        buttonConfig: {
            value: 'http://www.domain.de/test',
            type: 'link',
        },
        value: 'http://www.domain.de/test',
        type: 'link',
        prefix: 'http://',
        selector: '.sw-field--text input',
        label: 'sw-text-editor-toolbar.link.linkTo',
        placeholder: 'sw-text-editor-toolbar.link.placeholder',
    },
    {
        buttonConfig: {
            value: 'tel:01234567890123',
            type: 'phone',
        },
        value: '01234567890123',
        type: 'phone',
        prefix: 'tel:',
        selector: '.sw-field--text input',
        label: 'sw-text-editor-toolbar.link.linkTo',
        placeholder: 'sw-text-editor-toolbar.link.placeholderPhoneNumber',
    },
    {
        buttonConfig: {
            value: 'puppy.png?ts=1719991125',
            type: 'media',
        },
        value: 'puppy.png?ts=1719991125',
        type: 'media',
        prefix: '124c71d524604ccbad6042edce3ac799/mediaId/',
        selector: '.sw-field--media input',
        label: 'sw-text-editor-toolbar.link.linkTo',
    },
    {
        buttonConfig: {
            value: 'mailto:test@shopware.com',
            type: 'email',
        },
        value: 'test@shopware.com',
        type: 'email',
        prefix: 'mailto:',
        selector: '.sw-field--email input',
        label: 'sw-text-editor-toolbar.link.linkTo',
        placeholder: 'sw-text-editor-toolbar.link.placeholderEmail',
    },
    {
        buttonConfig: {
            value: `${seoDomainPrefix}/detail/aaaaaaa524604ccbad6042edce3ac799#`,
            type: 'detail',
        },
        value: 'aaaaaaa524604ccbad6042edce3ac799',
        type: 'detail',
        prefix: `${seoDomainPrefix}/detail/`,
        selector: '.sw-text-editor-link-menu__entity-single-select input',
        label: 'sw-text-editor-toolbar.link.linkTo',
        placeholder: 'sw-text-editor-toolbar.link.placeholderProduct',
    },
];

async function createWrapper(buttonConfig) {
    return mount(await wrapTestComponent('sw-text-editor-link-menu', { sync: true }), {
        global: {
            stubs: {
                'sw-select-field': await wrapTestComponent('sw-select-field', { sync: true }),
                'sw-select-field-deprecated': await wrapTestComponent('sw-select-field-deprecated', { sync: true }),
                'sw-switch-field': await wrapTestComponent('sw-switch-field'),
                'sw-switch-field-deprecated': await wrapTestComponent('sw-switch-field-deprecated', { sync: true }),
                'sw-email-field': await wrapTestComponent('sw-email-field'),
                'sw-email-field-deprecated': await wrapTestComponent('sw-email-field-deprecated'),
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                'sw-url-field': await wrapTestComponent('sw-url-field'),
                'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select'),
                'sw-category-tree-field': await wrapTestComponent('sw-category-tree-field'),
                'sw-media-field': await wrapTestComponent('sw-media-field'),
                'sw-media-modal-move': true,
                'sw-media-modal-replace': true,
                'sw-media-modal-delete': true,
                'sw-context-menu-item': true,
                'sw-media-preview-v2': true,
                'sw-pagination': true,
                'sw-simple-search-field': true,
                'sw-media-upload-v2': true,
                'sw-upload-listener': true,
                'sw-media-base-item': true,
                'sw-media-media-item': await wrapTestComponent('sw-media-media-item'),
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-product-variant-info': await wrapTestComponent('sw-product-variant-info'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-popover-deprecated': {
                    template: '<div class="sw-popover"><slot></slot></div>',
                },
                'sw-select-result': await wrapTestComponent('sw-select-result'),
                'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
                'sw-loader': true,
                'router-link': true,
                'mt-button': true,
                'sw-icon': true,
                'mt-select': true,
                'sw-help-text': true,
                'sw-ai-copilot-badge': true,
                'sw-inheritance-switch': true,
                'mt-switch': true,
                'sw-label': true,
                'sw-tree': true,
                'sw-checkbox-field': true,
                'sw-tree-item': true,
                'mt-text-field': true,
                'sw-field-copyable': true,
                'mt-email-field': true,
                'mt-floating-ui': true,
            },
        },
        props: {
            buttonConfig: {
                title: 'test',
                icon: '',
                expanded: true,
                newTab: true,
                displayAsButton: true,
                value: '',
                type: 'link',
                tag: 'a',
                active: false,
                ...buttonConfig,
            },
        },
        provide: {
            mediaService: {},
        },
    });
}

const responses = global.repositoryFactoryMock.responses;
const categoryData = {
    id: 'test-id',
    name: 'category-name',
    translated: {
        name: 'category-name',
    },
};

responses.addResponse({
    method: 'Post',
    url: '/search/category',
    status: 200,
    response: {
        data: [
            {
                id: 'test-id',
                attributes: categoryData,
                relationships: [],
            },
        ],
        meta: {
            total: 1,
        },
    },
});

const productData = [
    {
        id: 'aaaaaaa524604ccbad6042edce3ac799',
        attributes: {
            id: 'aaaaaaa524604ccbad6042edce3ac799',
            name: 'aaaaaaa524604ccbad6042edce3ac799',
        },
        relationships: [],
    },
    {
        id: 'some-id',
        attributes: {
            id: 'some-id',
            name: 'some-name',
        },
        relationships: [],
    },
];

responses.addResponse({
    method: 'Post',
    url: '/search/product',
    status: 200,
    response: {
        data: productData,
    },
});

responses.addResponse({
    method: 'Post',
    url: '/search/media',
    status: 200,
    response: {
        data: [
            {
                id: 'aaaaaaa524604ccbad6042edce3ac799',
                attributes: {
                    id: 'aaaaaaa524604ccbad6042edce3ac799',
                    fileName: 'puppy',
                    mediaFolderId: '01907293a32d718ea5a33a1e066730dd',
                    mimeType: 'image/png',
                    fileExtension: 'png',
                },
                relationships: [],
            },
        ],
    },
});

describe('components/form/sw-text-editor/sw-text-editor-link-menu', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    linkDataProvider.forEach((link) => {
        it(`parses ${link.type} URLs correctly`, async () => {
            const wrapper = await createWrapper(link.buttonConfig);
            await flushPromises();

            // Label should be set
            expect(wrapper.text()).toContain(link.label);

            // Element should have correct parsed value and placeholder
            const inputField = wrapper.find(link.selector);

            // sw-entity-single-select only uses the input field for the search
            if (
                ![
                    'detail',
                    'media',
                ].includes(link.type)
            ) {
                // eslint-disable-next-line jest/no-conditional-expect
                expect(inputField.element.value).toBe(link.value);
            }

            let placeholderId = 'some-id';
            if (!['media'].includes(link.type)) {
                // Placeholder should be set for all types
                // eslint-disable-next-line jest/no-conditional-expect
                expect(inputField.attributes('placeholder')).toBe(link.placeholder);

                await inputField.setValue(placeholderId);
            }

            // sw-entity-single-select specific changes
            if (link.type === 'detail') {
                await inputField.trigger('click');
                await flushPromises();

                await wrapper.find('.sw-select-option--1').trigger('click');

                placeholderId += '#';
            } else if (link.type === 'media') {
                await wrapper.find('.sw-media-field__toggle-button').trigger('click');
                await flushPromises();

                await wrapper.find('.sw-media-field__media-list-item').trigger('click');
                await flushPromises();

                placeholderId = `${link.value}#`;
            }

            await wrapper.find('.sw-text-editor-toolbar-button__link-menu-buttons-button-insert').trigger('click');
            await flushPromises();

            const dispatchedInputEvents = wrapper.emitted('button-click');

            expect(dispatchedInputEvents[0]).toStrictEqual([
                {
                    buttonVariant: undefined,
                    displayAsButton: true,
                    newTab: true,
                    type: 'link',
                    value: link.prefix + placeholderId,
                },
            ]);
        });
    });

    it('parses product detail links and reacts to changes correctly', async () => {
        const wrapper = await createWrapper({
            value: `${seoDomainPrefix}/detail/aaaaaaa524604ccbad6042edce3ac799#`,
            type: 'detail',
        });

        await flushPromises();

        const productSingleSelectInput = wrapper.find('.sw-text-editor-link-menu__entity-single-select input');
        await productSingleSelectInput.trigger('click');

        await flushPromises();

        expect(wrapper.text()).toContain('sw-text-editor-toolbar.link.linkTo');
        expect(productSingleSelectInput.element.placeholder).toBe('sw-text-editor-toolbar.link.placeholderProduct');

        const productSingleSelect = wrapper.findComponent('.sw-entity-single-select').vm;
        expect(productSingleSelect.entity).toBe('product');
        expect(productSingleSelect.value).toBe('aaaaaaa524604ccbad6042edce3ac799');

        expect(productSingleSelect.criteria).toStrictEqual(
            expect.objectContaining({
                limit: 25,
                page: 1,
            }),
        );

        const associations = productSingleSelect.criteria.associations;

        expect(associations).toHaveLength(1);
        expect(associations[0].association).toBe('options');

        expect(associations[0].criteria.associations).toHaveLength(1);
        expect(associations[0].criteria.associations[0].association).toBe('group');

        expect(productSingleSelect.criteria.filters).toStrictEqual(
            expect.objectContaining([
                {
                    operator: 'OR',
                    queries: [
                        {
                            field: 'product.childCount',
                            type: 'equals',
                            value: 0,
                        },
                        {
                            field: 'product.childCount',
                            type: 'equals',
                            value: null,
                        },
                    ],
                    type: 'multi',
                },
            ]),
        );

        const results = productSingleSelect.resultCollection;
        expect(results).toHaveLength(2);
        expect(results[0]).toEqual(productData[0].attributes);
        expect(results[1]).toEqual(productData[1].attributes);

        // Valid value set
        await productSingleSelect.setValue(productData[1]);
        await wrapper.find('.sw-text-editor-toolbar-button__link-menu-buttons-button-insert').trigger('click');
        await flushPromises();

        const dispatchedInputEvents = wrapper.emitted('button-click');
        expect(dispatchedInputEvents[0]).toStrictEqual([
            {
                buttonVariant: undefined,
                displayAsButton: true,
                newTab: true,
                type: 'link',
                value: '124c71d524604ccbad6042edce3ac799/detail/some-id#',
            },
        ]);

        // No value set
        await productSingleSelect.setValue({ id: null });
        await flushPromises();

        const isDisabled = wrapper
            .findComponent('.sw-text-editor-toolbar-button__link-menu-buttons-button-insert')
            .attributes('disabled');
        expect(isDisabled).toBeDefined();
    });

    it('parses category links and reacts to changes correctly', async () => {
        const wrapper = await createWrapper({
            value: `${seoDomainPrefix}/navigation/aaaaaaa524604ccbad6042edce3ac799#`,
            type: 'navigation',
        });

        await flushPromises();

        const categoryTreeFieldElement = wrapper.find('.sw-category-tree-field input');

        expect(wrapper.text()).toContain('sw-text-editor-toolbar.link.linkTo');
        expect(categoryTreeFieldElement.element.placeholder).toBe('sw-text-editor-toolbar.link.placeholderCategory');

        const categoryTreeField = wrapper.findComponent({
            name: 'sw-category-tree-field__wrapped',
        }).vm;

        expect(categoryTreeField.categoryCriteria).toStrictEqual(
            expect.objectContaining({
                limit: 500,
                page: 1,
            }),
        );

        expect(categoryTreeField.categoryCriteria.associations).toHaveLength(0);
        expect(categoryTreeField.categoryCriteria.filters).toHaveLength(0);

        expect(categoryTreeField.categoriesCollection).toHaveLength(1);
        expect(categoryTreeField.categoriesCollection[0]).toEqual(categoryData);

        categoryTreeField.$emit('selection-add', {
            id: 'new-selection',
        });
        await flushPromises();

        await wrapper.find('.sw-text-editor-toolbar-button__link-menu-buttons-button-insert').trigger('click');
        await flushPromises();

        const dispatchedInputEvents = wrapper.emitted('button-click');

        expect(dispatchedInputEvents[0]).toStrictEqual([
            {
                buttonVariant: undefined,
                displayAsButton: true,
                newTab: true,
                type: 'link',
                value: '124c71d524604ccbad6042edce3ac799/navigation/new-selection#',
            },
        ]);

        categoryTreeField.$emit('selection-remove');
        await flushPromises();

        const isDisabled = wrapper
            .findComponent('.sw-text-editor-toolbar-button__link-menu-buttons-button-insert')
            .attributes('disabled');
        expect(isDisabled).toBeDefined();
    });

    it('should clear the state if the link category is changed', async () => {
        const wrapper = await createWrapper({
            value: 'http://www.domain.de/test',
            type: 'link',
        });

        await flushPromises();

        expect(wrapper.vm.linkCategory).toBe('link');

        await wrapper.get('select').setValue('email');
        await flushPromises();

        expect(wrapper.vm.linkCategory).toBe('email');

        const isDisabled = wrapper
            .findComponent('.sw-text-editor-toolbar-button__link-menu-buttons-button-insert')
            .attributes('disabled');
        expect(isDisabled).toBeDefined();
    });

    it('should clear the linkTarget when the remove button is pressed', async () => {
        const wrapper = await createWrapper({
            value: 'http://www.domain.de/test',
            type: 'link',
        });

        await flushPromises();

        expect(wrapper.vm.linkCategory).toBe('link');
        expect(wrapper.vm.linkTarget).toBe('http://www.domain.de/test');

        wrapper.findComponent('.sw-text-editor-toolbar-button__link-menu-buttons-button-remove').vm.$emit('click');
        await flushPromises();

        const dispatchedInputEvents = wrapper.emitted('button-click');

        expect(dispatchedInputEvents[0]).toStrictEqual([
            {
                type: 'linkRemove',
            },
        ]);
    });
});
