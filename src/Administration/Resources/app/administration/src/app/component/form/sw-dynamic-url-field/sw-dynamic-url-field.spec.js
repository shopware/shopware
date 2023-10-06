/**
 * @package admin
 */

import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/form/sw-dynamic-url-field';

const seoDomainPrefix = '124c71d524604ccbad6042edce3ac799';

const linkDataProvider = [{
    URL: 'http://www.domain.de/test',
    value: 'http://www.domain.de/test',
    type: 'link',
    prefix: '',
    selector: '.sw-text-field',
    label: 'sw-text-editor-toolbar.link.linkTo',
    placeholder: 'sw-text-editor-toolbar.link.placeholder',
}, {
    URL: 'tel:01234567890123',
    value: '01234567890123',
    type: 'phone',
    prefix: 'tel:',
    selector: '.sw-text-field',
    label: 'sw-text-editor-toolbar.link.linkTo',
    placeholder: 'sw-text-editor-toolbar.link.placeholderPhoneNumber',
}, {
    URL: 'mailto:test@shopware.com',
    value: 'test@shopware.com',
    type: 'email',
    prefix: 'mailto:',
    selector: '.sw-email-field',
    label: 'sw-text-editor-toolbar.link.linkTo',
    placeholder: 'sw-text-editor-toolbar.link.placeholderEmail',
}, {
    URL: `${seoDomainPrefix}/detail/aaaaaaa524604ccbad6042edce3ac799#`,
    value: 'aaaaaaa524604ccbad6042edce3ac799',
    type: 'detail',
    prefix: `${seoDomainPrefix}/detail/`,
    selector: '.sw-entity-single-select',
    label: 'sw-text-editor-toolbar.link.linkTo',
    placeholder: 'sw-text-editor-toolbar.link.placeholderProduct',
}];


async function createWrapper(startingValue) {
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-dynamic-url-field'), {
        localVue,
        stubs: {
            'sw-select-field': {
                template: '<select class="sw-select-field" :value="value" @change="$emit(\'change\', $event.target.value)"><slot></slot></select>',
                props: ['value'],
            },
            'sw-switch-field': {
                props: ['value', 'label', 'placeholder'],
                template: '<input class="sw-switch-field" type="checkbox" :value="value" @input="$emit(\'input\', $event.target.value)" />',
            },
            'sw-email-field': {
                props: ['value', 'label', 'placeholder'],
                template: '<input class="sw-email-field" :value="value" @input="$emit(\'input\', $event.target.value)" />',
            },
            'sw-text-field': {
                props: ['value', 'label', 'placeholder'],
                template: '<input class="sw-text-field" :value="value" @input="$emit(\'input\', $event.target.value)" />',
            },
            'sw-entity-single-select': {
                props: ['value', 'label', 'placeholder'],
                template: '<input class="sw-entity-single-select" :value="value" @input="$emit(\'input\', $event.target.value)">',
            },
            'sw-category-tree-field': {
                props: ['label', 'placeholder', 'criteria', 'categories-collection'],
                template: '<div class="sw-category-tree-field"></div>',
            },
            'sw-button': true,
        },
        propsData: {
            value: startingValue,
        },
    });
}

const responses = global.repositoryFactoryMock.responses;
const categoryData = {
    id: 'test-id',
    name: 'category-name',
};

responses.addResponse({
    method: 'Post',
    url: '/search/category',
    status: 200,
    response: {
        data: [{
            id: 'test-id',
            attributes: categoryData,
            relationships: [],
        }],
        meta: {
            total: 1,
        },
    },
});

describe('components/form/sw-text-editor/sw-text-editor-link-menu', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    linkDataProvider.forEach(link => {
        it(`parses ${link.type} URL's correctly`, async () => {
            const wrapper = await createWrapper(link.URL);

            await wrapper.vm.$nextTick();
            await wrapper.vm.$nextTick();

            const inputField = wrapper.find(link.selector);
            expect(inputField.props()).toStrictEqual(
                expect.objectContaining({
                    value: link.value,
                    label: link.label,
                    placeholder: link.placeholder,
                }),
            );

            let placeholderId = 'some-id';
            await inputField.setValue(placeholderId);

            if (link.type === 'detail') {
                placeholderId += '#';
            }

            const dispatchedInputEvents = wrapper.emitted('input');

            expect(dispatchedInputEvents[0]).toStrictEqual([link.prefix + placeholderId]);
        });
    });

    it('parses category links and reacts to changes correctly', async () => {
        const wrapper = await createWrapper(`${seoDomainPrefix}/navigation/aaaaaaa524604ccbad6042edce3ac799#`);

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const categoryTreeField = wrapper.find('.sw-category-tree-field');
        const props = categoryTreeField.props();

        expect(props.label).toBe('sw-text-editor-toolbar.link.linkTo');
        expect(props.placeholder).toBe('sw-text-editor-toolbar.link.placeholderCategory');
        expect(props.criteria).toStrictEqual(
            expect.objectContaining({
                limit: 25,
                page: 1,
            }),
        );

        const associations = props.criteria.associations;

        expect(associations).toHaveLength(1);
        expect(associations[0].association).toBe('options');

        expect(associations[0].criteria.associations).toHaveLength(1);
        expect(associations[0].criteria.associations[0].association).toBe('group');

        expect(props.criteria.filters).toStrictEqual(expect.objectContaining(
            [{
                operator: 'OR',
                queries: [
                    { field: 'product.childCount', type: 'equals', value: 0 },
                    { field: 'product.childCount', type: 'equals', value: null },
                ],
                type: 'multi',
            }],
        ));

        expect(props.categoriesCollection).toHaveLength(1);
        expect(props.categoriesCollection[0]).toEqual(categoryData);

        categoryTreeField.vm.$emit('selection-add', {
            id: 'new-selection',
        });
        await wrapper.vm.$nextTick();

        const dispatchedInputEvents = wrapper.emitted('input');

        expect(dispatchedInputEvents[0]).toStrictEqual(['124c71d524604ccbad6042edce3ac799/navigation/new-selection#']);

        categoryTreeField.vm.$emit('selection-remove');
        await wrapper.vm.$nextTick();

        expect(dispatchedInputEvents[1]).toStrictEqual(['']);
    });

    it('should clear the state if the link category is changed', async () => {
        const wrapper = await createWrapper('http://www.domain.de/test');

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.linkCategory).toBe('link');

        const options = wrapper.find('select').findAll('option');
        await options.at(3).setSelected();

        expect(wrapper.vm.linkCategory).toBe('email');

        const dispatchedInputEvents = wrapper.emitted('input');
        expect(dispatchedInputEvents[0]).toStrictEqual(['']);
    });

    it('should clear the linkTarget when the remove button is pressed', async () => {
        const wrapper = await createWrapper('http://www.domain.de/test');

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.linkCategory).toBe('link');
        expect(wrapper.vm.linkTarget).toBe('http://www.domain.de/test');

        wrapper.find('.sw-dynamic-url-field__link-menu-buttons-button-remove').vm.$emit('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.linkCategory).toBe('link');
        expect(wrapper.vm.linkTarget).toBe('');
    });
});
