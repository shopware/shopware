/*
 * @package inventory
 */

import { mount } from '@vue/test-utils_v3';

const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: 'Post',
    url: '/search/custom-field-set',
    status: 200,
    response: {
        data: [
            {
                id: 'custom_field_set_id1',
                type: 'custom_field_set',
                attributes: {
                    name: 'custom_field_set_1',
                    config: { label: { 'en-GB': 'Field Set 1' } },
                    active: true,
                    global: false,
                    position: 1,
                    appId: null,
                },
                relationships: {
                    customFields: {
                        data: [{
                            type: 'custom_field',
                            id: 'custom_field_id1',
                        }],
                        links: { related: 'http://host/api/custom-field-set/custom_field_set_id1/custom-fields' },
                    },
                },
            },
        ],
        included: [
            {
                id: 'custom_field_id1',
                type: 'custom_field',
                attributes: {
                    name: 'custom_field_1',
                    type: 'int',
                    config: {
                        type: 'number',
                        label: { 'en-GB': 'First custom field number' },
                        numberType: 'int',
                        placeholder: { 'en-GB': 'Type a number...' },
                        componentName: 'sw-field',
                        customFieldType: 'number',
                        customFieldPosition: 1,
                    },
                    active: true,
                    customFieldSetId: 'custom_field_set_id1',
                },
                relationships: {
                    customFieldSet: {
                    },
                },
            }, {
                id: 'custom_field_set_relation_id1',
                type: 'custom_field_set_relation',
                attributes: {
                    customFieldSetId: 'custom_field_set_id1',
                    entityName: 'customer',
                },
                relationships: {
                    customFieldSet: {},
                },
            }],
    },
});

async function createWrapper() {
    return mount(await wrapTestComponent('sw-product-stream-detail', { sync: true }), {
        props: {
            productStreamId: null,
        },
        global: {
            stubs: {
                'sw-page': {
                    template: `
    <div>
        <slot name="smart-bar-actions"></slot>
        <slot name="content"></slot>
    </div>`,
                },
                'sw-button': true,
                'sw-button-group': true,
                'sw-button-process': true,
                'sw-context-button': true,
                'sw-icon': true,
                'sw-context-menu-item': true,
                'sw-card-view': true,
                'sw-skeleton': true,
                'sw-card': true,
                'sw-language-info': true,
                'sw-text-field': true,
                'sw-textarea-field': true,
                'sw-condition-tree': true,
            },
            provide: {
                customFieldDataProviderService: {
                    getCustomFieldSets: () => Promise.resolve({}),
                },
                productStreamConditionService: {},
            },
        },
    });
}

describe('src/module/sw-product-stream/page/sw-product-stream-detail', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should fetch custom product custom fields and add them to the condition select list', async () => {
        const wrapper = await createWrapper();

        await flushPromises();

        const relatedCustomFields = wrapper.vm.productCustomFields;
        expect(relatedCustomFields).toHaveProperty('custom_field_1');
    });
});
