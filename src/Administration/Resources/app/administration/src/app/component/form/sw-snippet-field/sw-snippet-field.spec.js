import { createLocalVue, shallowMount } from '@vue/test-utils';
import uuid from 'src/../test/_helper_/uuid';
import 'src/app/component/base/sw-icon';
import 'src/app/component/form/sw-snippet-field';
import flushPromises from 'flush-promises';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';

function createWrapper(systemLanguageIso = '', translations = [], customOptions = {}) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-snippet-field'), {
        localVue,
        propsData: {
            snippet: 'test.snippet'
        },
        stubs: {
            'sw-field': Shopware.Component.build('sw-field'),
            'sw-text-field': Shopware.Component.build('sw-text-field'),
            'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-field-error': Shopware.Component.build('sw-field-error'),
            'sw-modal': true,
            'sw-loader': true,
            'sw-icon': true,
            'sw-snippet-field-edit-modal': true
        },
        provide: {
            validationService: {},
            repositoryFactory: {
                create: (entity) => ({
                    search: () => {
                        if (entity === 'snippet_set') {
                            return Promise.resolve(createEntityCollection([
                                {
                                    name: 'Base en-GB',
                                    iso: 'en-GB',
                                    id: uuid.get('en-GB')
                                },
                                {
                                    name: 'Base de-DE',
                                    iso: 'de-DE',
                                    id: uuid.get('de-DE')
                                }
                            ]));
                        }

                        if (entity === 'language') {
                            return Promise.resolve(createEntityCollection([
                                {
                                    name: 'default language',
                                    locale: {
                                        code: systemLanguageIso
                                    },
                                    id: uuid.get('default language')
                                }
                            ]));
                        }

                        return Promise.resolve([]);
                    }
                })
            },
            snippetSetService: {
                getCustomList: () => {
                    return Promise.resolve({
                        total: translations.length,
                        data: {
                            'test.snippet': translations
                        }
                    });
                }
            }
        },
        ...customOptions
    });
}

function createEntityCollection(entities = []) {
    return new Shopware.Data.EntityCollection('collection', 'collection', {}, null, entities);
}

describe('src/app/component/form/sw-snippet-field', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show admin language translation of snippet field', async () => {
        Shopware.State.get('session').currentLocale = 'de-DE';

        const wrapper = createWrapper('en-GB', [{
            author: 'testUser',
            id: null,
            value: 'english',
            origin: null,
            resetTo: 'english',
            translationKey: 'test.snippet',
            setId: uuid.get('en-GB')
        }, {
            author: 'testUser',
            id: null,
            value: 'deutsch',
            origin: null,
            resetTo: 'deutsch',
            translationKey: 'test.snippet',
            setId: uuid.get('de-DE')
        }]);

        await flushPromises();

        const textField = wrapper.find('input');
        expect(textField.element.value).toBe('deutsch');
    });

    it('should show system default language translation of snippet field', async () => {
        Shopware.State.get('session').currentLocale = 'nl-NL';

        const wrapper = createWrapper('de-DE', [{
            author: 'testUser',
            id: null,
            value: 'english',
            origin: null,
            resetTo: 'english',
            translationKey: 'test.snippet',
            setId: uuid.get('en-GB')
        }, {
            author: 'testUser',
            id: null,
            value: 'deutsch',
            origin: null,
            resetTo: 'deutsch',
            translationKey: 'test.snippet',
            setId: uuid.get('de-DE')
        }]);

        await flushPromises();

        const textField = wrapper.find('input');
        expect(textField.element.value).toBe('deutsch');
    });

    it('should show en-GB language translation of snippet field', async () => {
        Shopware.State.get('session').currentLocale = 'nl-NL';

        const wrapper = createWrapper('nl-NL', [{
            author: 'testUser',
            id: null,
            value: 'english',
            origin: null,
            resetTo: 'english',
            translationKey: 'test.snippet',
            setId: uuid.get('en-GB')
        }, {
            author: 'testUser',
            id: null,
            value: 'deutsch',
            origin: null,
            resetTo: 'deutsch',
            translationKey: 'test.snippet',
            setId: uuid.get('de-DE')
        }]);

        await flushPromises();

        const textField = wrapper.find('input');
        expect(textField.element.value).toBe('english');
    });

    it('should show snippet key as fallback', async () => {
        Shopware.State.get('session').currentLocale = 'nl-NL';

        const wrapper = createWrapper('nl-NL', []);

        await flushPromises();

        const textField = wrapper.find('input');
        expect(textField.element.value).toBe('test.snippet');
    });

    it('should display and hide edit modal', async () => {
        Shopware.State.get('session').currentLocale = 'en-GB';
        Shopware.State.get('session').currentUser = {
            username: 'testUser'
        };

        const wrapper = createWrapper('en-GB', []);

        await flushPromises();

        expect(wrapper.find('sw-snippet-field-edit-modal-stub').exists()).toBe(false);

        wrapper.get('.sw-snippet-field__icon').vm.$emit('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.find('sw-snippet-field-edit-modal-stub').exists()).toBe(true);

        wrapper.vm.closeEditModal();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('sw-snippet-field-edit-modal-stub').exists()).toBe(false);
    });
});
