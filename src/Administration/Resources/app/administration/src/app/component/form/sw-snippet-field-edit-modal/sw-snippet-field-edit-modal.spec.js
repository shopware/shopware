/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

import 'src/app/component/form/sw-snippet-field-edit-modal';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-snippet-field-edit-modal', { sync: true }), {
        props: {
            translationKey: 'test.snippet',
            fieldType: 'text',
            snippets: [{
                author: 'testUser',
                id: null,
                value: 'english',
                origin: null,
                resetTo: 'english',
                translationKey: 'test.snippet',
                setId: 'en-GB-MOCK-ID',
            }, {
                author: 'testUser',
                id: null,
                value: 'deutsch',
                origin: null,
                resetTo: 'deutsch',
                translationKey: 'test.snippet',
                setId: 'de-DE-MOCK-ID',
            }],
            snippetSets: createEntityCollection([
                {
                    name: 'Base en-GB',
                    iso: 'en-GB',
                    id: 'en-GB-MOCK-ID',
                },
                {
                    name: 'Base de-DE',
                    iso: 'de-DE',
                    id: 'de-DE-MOCK-ID',
                },
            ]),
        },
        global: {
            stubs: {
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                'sw-loader': true,
                'sw-icon': true,
                'sw-modal': await wrapTestComponent('sw-modal'),
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
            },
            provide: {
                validationService: {},
                snippetService: {
                    save: () => {},
                },
                shortcutService: {
                    stopEventListener: () => {},
                    startEventListener: () => {},
                },
            },
        },
    });
}

function createEntityCollection(entities = []) {
    return new Shopware.Data.EntityCollection('collection', 'collection', {}, null, entities);
}

describe('src/app/component/form/sw-snippet-field-edit-modal', () => {
    it('should disable the inputs when the user has no right to edit snippets', async () => {
        global.activeAclRoles = ['snippet.viewer'];

        const wrapper = await createWrapper();
        await flushPromises();

        const [firstInput, secondInput] = wrapper.findAll('.sw-snippet-field-edit-modal__translation-field');

        expect(firstInput.wrapperElement).toBeDisabled();
        expect(secondInput.wrapperElement).toBeDisabled();
    });

    it.each([
        ['snippet.viewer', 'snippet.editor'],
        ['snippet.viewer', 'snippet.editor', 'snippet.creator'],
        ['snippet.viewer', 'snippet.editor', 'snippet.deleter'],
    ])('should have enabled inputs when the user has the appropriate roles', async (...roles) => {
        global.activeAclRoles = roles;
        const wrapper = await createWrapper();
        await flushPromises();

        const [firstInput, secondInput] = wrapper.findAll('.sw-snippet-field-edit-modal__translation-field');

        expect(firstInput.wrapperElement).toBeEnabled();
        expect(secondInput.wrapperElement).toBeEnabled();
    });

    it('should have a disabled save button', async () => {
        global.activeAclRoles = ['snippet.viewer'];
        const wrapper = await createWrapper();

        const saveButton = wrapper.find('.sw-snippet-field-edit-modal__save-action');

        expect(saveButton.wrapperElement).toBeDisabled();
    });
});
