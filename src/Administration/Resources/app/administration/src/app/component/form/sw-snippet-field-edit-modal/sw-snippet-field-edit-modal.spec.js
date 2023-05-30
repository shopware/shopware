/**
 * @package admin
 */

import { shallowMount } from '@vue/test-utils';
import 'src/app/component/base/sw-icon';
import 'src/app/component/base/sw-modal';
import 'src/app/component/base/sw-button';
import 'src/app/component/form/sw-snippet-field';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/form/sw-snippet-field-edit-modal';

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-snippet-field-edit-modal'), {
        propsData: {
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
        stubs: {
            'sw-field': {
                template: '<input class="sw-field"></input>',
                props: ['value', 'disabled'],
            },
            'sw-loader': true,
            'sw-icon': true,
            'sw-modal': await Shopware.Component.build('sw-modal'),
            'sw-button': {
                template: '<button class="sw-button"></button>',
                props: ['disabled'],
            },
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
    });
}

function createEntityCollection(entities = []) {
    return new Shopware.Data.EntityCollection('collection', 'collection', {}, null, entities);
}

describe('src/app/component/form/sw-snippet-field-edit-modal', () => {
    let wrapper;

    afterEach(() => {
        if (wrapper) {
            wrapper.destroy();
        }
    });

    it('should be a Vue.JS component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    ([{
        shouldBeDisabled: true,
        roles: ['snippet.viewer'],
    }, {
        shouldBeDisabled: false,
        roles: ['snippet.viewer', 'snippet.editor'],
    }, {
        shouldBeDisabled: false,
        roles: ['snippet.viewer', 'snippet.editor', 'snippet.creator'],
    }, {
        shouldBeDisabled: false,
        roles: ['snippet.viewer', 'snippet.editor', 'snippet.deleter'],
    }]).forEach((testcase) => {
        it(`should have ${testcase.shouldBeDisabled ? '' : 'not'} disabled inputs with roles ${testcase.roles.join(', ')}`, async () => {
            global.activeAclRoles = testcase.roles;
            wrapper = await createWrapper();

            await wrapper.vm.$nextTick();

            const [firstInput, secondInput] = wrapper.findAll('.sw-snippet-field-edit-modal__translation-field').wrappers;

            expect(firstInput.props('disabled')).toBe(testcase.shouldBeDisabled);
            expect(secondInput.props('disabled')).toBe(testcase.shouldBeDisabled);
        });
    });

    it('should have a disabled save button', async () => {
        global.activeAclRoles = ['snippet.viewer'];
        wrapper = await createWrapper();
        const saveButton = wrapper.find('.sw-snippet-field-edit-modal__save-action');

        expect(saveButton.props('disabled')).toBe(true);
    });
});
