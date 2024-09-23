/**
 * @package admin
 */

import { mount } from '@vue/test-utils';
import uuid from 'src/../test/_helper_/uuid';

async function createWrapper(systemLanguageIso = '', translations = [], customOptions = {}) {
    return mount(await wrapTestComponent('sw-snippet-field', { sync: true }), {
        global: {
            directives: {
                tooltip: {},
            },
            stubs: {
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-modal': true,
                'sw-loader': true,
                'sw-icon': true,
                'sw-snippet-field-edit-modal': true,
                'sw-help-text': true,
                'sw-textarea-field': true,
                'sw-ai-copilot-badge': true,
                'sw-inheritance-switch': true,
                'sw-field-copyable': true,
                'mt-text-field': true,
            },
            provide: {
                validationService: {},
                repositoryFactory: {
                    create: (entity) => ({
                        search: () => {
                            if (entity === 'snippet_set') {
                                const mockLanguages = (new Array(30)).fill(null).reduce((accumulator, _, index) => {
                                    accumulator.push({
                                        name: `Mock en-GB #${index}`,
                                        iso: `en-GB-${index}`,
                                        id: uuid.get('en-GB'),
                                    });

                                    return accumulator;
                                }, []);

                                return Promise.resolve(createEntityCollection([
                                    {
                                        name: 'Base en-GB',
                                        iso: 'en-GB',
                                        id: uuid.get('en-GB'),
                                    },
                                    {
                                        name: 'Base de-DE',
                                        iso: 'de-DE',
                                        id: uuid.get('de-DE'),
                                    },
                                    ...mockLanguages,
                                ]));
                            }

                            if (entity === 'language') {
                                return Promise.resolve(createEntityCollection([
                                    {
                                        name: 'default language',
                                        locale: {
                                            code: systemLanguageIso,
                                        },
                                        id: uuid.get('default language'),
                                    },
                                ]));
                            }

                            return Promise.resolve([]);
                        },
                    }),
                },
                snippetSetService: {
                    getCustomList: () => {
                        return Promise.resolve({
                            total: translations.length,
                            data: {
                                'test.snippet': translations,
                            },
                        });
                    },
                },
            },
        },
        props: {
            snippet: 'test.snippet',
        },
        ...customOptions,
    });
}

function createEntityCollection(entities = []) {
    return new Shopware.Data.EntityCollection('collection', 'collection', {}, null, entities);
}

describe('src/app/component/form/sw-snippet-field', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show admin language translation of snippet field', async () => {
        Shopware.State.get('session').currentLocale = 'de-DE';

        const wrapper = await createWrapper('en-GB', [{
            author: 'testUser',
            id: null,
            value: 'english',
            origin: null,
            resetTo: 'english',
            translationKey: 'test.snippet',
            setId: uuid.get('en-GB'),
        }, {
            author: 'testUser',
            id: null,
            value: 'deutsch',
            origin: null,
            resetTo: 'deutsch',
            translationKey: 'test.snippet',
            setId: uuid.get('de-DE'),
        }]);

        await flushPromises();

        const textField = wrapper.find('input');
        expect(textField.element.value).toBe('deutsch');
    });

    it('should show all admin languages\' translations of snippet field, even with more than 25 languages', async () => {
        Shopware.State.get('session').currentLocale = 'de-DE';

        const enGB = {
            author: 'testUser',
            id: null,
            value: 'english',
            origin: null,
            resetTo: 'english',
            translationKey: 'test.snippet',
            setId: uuid.get('en-GB'),
        };

        const deDE = {
            ...enGB,
            value: 'deutsch',
            resetTo: 'deutsch',
            setId: uuid.get('de-DE'),
        };

        const mockLanguages = (new Array(30)).reduce((accumulator, _, index) => {
            accumulator.push({
                ...enGB,
                value: `mock-english-${index}`,
                resetTo: `mock-english-${index}`,
            });

            return accumulator;
        }, []);

        const wrapper = await createWrapper('en-GB', [
            enGB,
            deDE,
            ...mockLanguages,
        ]);

        await flushPromises();

        expect(wrapper.vm.snippetSets).toHaveLength(32);
    });

    it('should show system default language translation of snippet field', async () => {
        Shopware.State.get('session').currentLocale = 'nl-NL';

        const wrapper = await createWrapper('de-DE', [{
            author: 'testUser',
            id: null,
            value: 'english',
            origin: null,
            resetTo: 'english',
            translationKey: 'test.snippet',
            setId: uuid.get('en-GB'),
        }, {
            author: 'testUser',
            id: null,
            value: 'deutsch',
            origin: null,
            resetTo: 'deutsch',
            translationKey: 'test.snippet',
            setId: uuid.get('de-DE'),
        }]);

        await flushPromises();

        const textField = wrapper.find('input');
        expect(textField.element.value).toBe('deutsch');
    });

    it('should show en-GB language translation of snippet field', async () => {
        Shopware.State.get('session').currentLocale = 'nl-NL';

        const wrapper = await createWrapper('nl-NL', [{
            author: 'testUser',
            id: null,
            value: 'english',
            origin: null,
            resetTo: 'english',
            translationKey: 'test.snippet',
            setId: uuid.get('en-GB'),
        }, {
            author: 'testUser',
            id: null,
            value: 'deutsch',
            origin: null,
            resetTo: 'deutsch',
            translationKey: 'test.snippet',
            setId: uuid.get('de-DE'),
        }]);

        await flushPromises();

        const textField = wrapper.find('input');
        expect(textField.element.value).toBe('english');
    });

    it('should show snippet key as fallback', async () => {
        Shopware.State.get('session').currentLocale = 'nl-NL';

        const wrapper = await createWrapper('nl-NL', []);

        await flushPromises();

        const textField = wrapper.find('input');
        expect(textField.element.value).toBe('test.snippet');
    });

    it('should display and hide edit modal', async () => {
        Shopware.State.get('session').currentLocale = 'en-GB';
        Shopware.State.get('session').currentUser = {
            username: 'testUser',
        };

        const wrapper = await createWrapper('en-GB', []);

        await flushPromises();

        expect(wrapper.find('sw-snippet-field-edit-modal-stub').exists()).toBe(false);

        wrapper.getComponent('.sw-snippet-field__icon').vm.$emit('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.find('sw-snippet-field-edit-modal-stub').exists()).toBe(true);

        wrapper.vm.closeEditModal();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('sw-snippet-field-edit-modal-stub').exists()).toBe(false);
    });
});
