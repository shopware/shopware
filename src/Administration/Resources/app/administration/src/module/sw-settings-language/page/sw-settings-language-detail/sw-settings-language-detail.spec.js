/**
 * @sw-package fundamentals@discovery
 */
import { mount } from '@vue/test-utils';

async function createWrapper(privileges = [], languageId = null, stubTranslationIsoField = true) {
    const languageRepositoryGet = jest.fn((id) => {
        return Promise.resolve({
            id,
            isNew: () => false,
            parentId: '1234',
            translationCodeId: '5678',
        });
    });

    const options = {
        props: {
            languageId,
        },
        global: {
            directives: {
                popover: Shopware.Directive.getDirectiveRegistry().get('popover'),
            },
            renderStubDefaultSlot: true,
            mocks: {
                $t(translationKey) {
                    return translationKey;
                },
            },
            provide: {
                repositoryFactory: {
                    create: (repositoryName) => ({
                        search: () => {
                            switch (repositoryName) {
                                case 'language':
                                    return Promise.resolve({
                                        aggregations: {
                                            usedTranslationIds: {
                                                buckets: [
                                                    {
                                                        key: '018d36e6165671b788b4811b31fdb2be',
                                                    },
                                                ],
                                            },
                                        },
                                    });
                                case 'locale': {
                                    return Promise.resolve([
                                        {
                                            id: '018d36e6165b702e8d73f463e7d38e87',
                                            code: 'nr-ZA',
                                            name: 'Southern Ndebele',
                                            territory: 'South Africa',
                                        },
                                        {
                                            id: '018d36e6165371a4b145cd683bf65869',
                                            code: 'de-DE',
                                            name: 'German',
                                            territory: 'Germany',
                                        },
                                        {
                                            id: '018d36e6165671b788b4811b31fdb2be',
                                            code: 'bs-BA',
                                            name: 'Bosnian',
                                            territory: 'Bosnia and Herzegovina',
                                        },
                                    ]);
                                }
                                default: {
                                    return Promise.resolve();
                                }
                            }
                        },

                        create: () => {
                            return Promise.resolve({
                                isNew: () => true,
                            });
                        },

                        get: languageRepositoryGet,

                        save: () => {
                            return Promise.resolve();
                        },
                    }),
                },
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
                customFieldDataProviderService: {
                    getCustomFieldSets: () => Promise.resolve([]),
                },
                translationService: {
                    getList: jest.fn().mockResolvedValue({
                        total: 0,
                        items: [],
                    }),
                    getMeta: jest.fn().mockResolvedValue({
                        builtInLocales: [
                            'de-DE',
                            'en-GB',
                        ],
                    }),
                    install: jest.fn().mockResolvedValue(undefined),
                },
            },
            stubs: {
                'sw-page': {
                    template: `
                    <div class="sw-page">
                        <slot name="search-bar"></slot>
                        <slot name="smart-bar-back"></slot>
                        <slot name="smart-bar-header"></slot>
                        <slot name="language-switch"></slot>
                        <slot name="smart-bar-actions"></slot>
                        <slot name="side-content"></slot>
                        <slot name="content"></slot>
                        <slot name="sidebar"></slot>
                        <slot></slot>
                    </div>
                `,
                },
                'sw-card-view': true,
                'sw-container': true,
                'sw-language-switch': true,
                'sw-button-process': true,
                'sw-text-field': true,
                'sw-entity-single-select': true,
                'sw-skeleton': true,
                'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper'),
                'sw-inheritance-switch': true,
                'sw-highlight-text': true,
                'sw-select-result': true,

                'sw-custom-field-set-renderer': true,
                'sw-product-variant-info': true,
                'sw-loader': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
                'sw-field-error': true,
                'router-link': true,
            },
        },
    };

    if (stubTranslationIsoField === false) {
        options.global.stubs = {
            ...options.global.stubs,
            'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select'),
            'sw-select-base': await wrapTestComponent('sw-select-base'),
            'sw-block-field': await wrapTestComponent('sw-block-field'),
            'sw-base-field': await wrapTestComponent('sw-base-field'),
            'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
            'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
            'sw-select-result': await wrapTestComponent('sw-select-result'),
            'sw-popover': await wrapTestComponent('sw-popover'),
            'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
        };
    }

    return mount(await wrapTestComponent('sw-settings-language-detail', { sync: true }), options);
}

describe('module/sw-settings-language/page/sw-settings-language-detail', () => {
    it('should return identifier', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.identifier).toBe('');

        wrapper.vm.language = {
            name: 'English',
        };

        expect(wrapper.vm.identifier).toBe('English');
    });

    it('should not be possible to inherit with no system language', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm.inheritanceTooltipText).toBe('sw-settings-language.detail.tooltipLanguageNotChoosable');

        wrapper.vm.language = {
            id: Shopware.Context.api.systemLanguageId,
        };
        expect(wrapper.vm.inheritanceTooltipText).toBe('sw-settings-language.detail.tooltipInheritanceNotPossible');
    });

    it('should load entity data', async () => {
        const wrapper = await createWrapper([], Shopware.Context.api.systemLanguageId);
        expect(wrapper.vm.languageId).toBe(Shopware.Context.api.systemLanguageId);
        await flushPromises();

        expect(wrapper.vm.language.id).toBe(Shopware.Context.api.systemLanguageId);
    });

    it('should be able to save the language', async () => {
        const wrapper = await createWrapper([
            'language.editor',
            null,
            false,
        ]);
        await flushPromises();

        const saveButton = wrapper.find('.sw-settings-language-detail__save-action');
        const languageNameField = wrapper.find('input[aria-label="sw-settings-language.detail.labelName"]');
        const languageParentIdField = wrapper.find(
            'sw-entity-single-select-stub[label="sw-settings-language.detail.labelParent"]',
        );
        const languageTranslationCodeIdField = wrapper.find('#iso-codes');
        const languageLocaleIdField = wrapper.find(
            'sw-entity-single-select-stub[label="sw-settings-language.detail.labelLocale"]',
        );

        expect(saveButton.attributes().disabled).toBeFalsy();
        expect(languageNameField.attributes().disabled).toBeUndefined();
        expect(languageParentIdField.attributes().disabled).toBeUndefined();
        expect(languageTranslationCodeIdField.attributes().disabled).toBeUndefined();
        expect(languageLocaleIdField.attributes().disabled).toBeUndefined();
    });

    it('should not be able to save the language', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const saveButton = wrapper.find('.sw-settings-language-detail__save-action');
        const languageNameField = wrapper.find('input[aria-label="sw-settings-language.detail.labelName"]');
        const languageParentIdField = wrapper.find(
            'sw-entity-single-select-stub[label="sw-settings-language.detail.labelParent"]',
        );
        const languageTranslationCodeIdField = wrapper.find('#iso-codes');
        const languageLocaleIdField = wrapper.find(
            'sw-entity-single-select-stub[label="sw-settings-language.detail.labelLocale"]',
        );

        expect(saveButton.attributes().disabled).toBeTruthy();
        expect(languageNameField.attributes().disabled).toBeDefined();
        expect(languageParentIdField.attributes().disabled).toBeTruthy();
        expect(languageTranslationCodeIdField.attributes().disabled).toBeTruthy();
        expect(languageLocaleIdField.attributes().disabled).toBeTruthy();
    });

    it('should add an asterix to used iso codes', async () => {
        const wrapper = await createWrapper(['language.editor'], Shopware.Context.api.systemLanguageId, false);
        await flushPromises();

        const languageTranslationCodeIdField = wrapper.find('#iso-codes');

        await languageTranslationCodeIdField.find('.sw-entity-single-select__selection').trigger('click');
        await flushPromises();

        expect(document.body.querySelector('.sw-select-option--0').classList).not.toContain('is--disabled');

        document.body.querySelector('.sw-select-option--0').click();
        await flushPromises();

        await languageTranslationCodeIdField.find('.sw-entity-single-select__selection').trigger('click');
        await flushPromises();

        expect(document.body.querySelector('.sw-select-option--2').textContent).toContain('*');

        document.body.querySelector('.sw-select-option--2').click();
        await flushPromises();

        expect(wrapper.find('.sw-field__hint').text()).toContain('textIsoCodeIsInUse');
    });

    it('should load language data again after create new language', async () => {
        const wrapper = await createWrapper(
            [
                'language.editor',
            ],
            null,
            false,
        );
        await flushPromises();

        const actionLoadEntitySpy = jest.spyOn(wrapper.vm, 'loadEntityData');
        expect(actionLoadEntitySpy).not.toHaveBeenCalled();

        await wrapper.setProps({
            languageId: 'language-id-1',
        });

        expect(actionLoadEntitySpy).toHaveBeenCalledTimes(1);
    });

    it('renders the created success banner only while justCreated is set', async () => {
        const wrapper = await createWrapper(['language.creator'], null);
        await flushPromises();

        expect(wrapper.find('.sw-settings-language-detail__created-banner').exists()).toBe(false);

        wrapper.vm.justCreated = true;
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-settings-language-detail__created-banner').exists()).toBe(true);
    });

    it('derives the snippet update state from the locale and metadata', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.builtInLocales = [
            'de-DE',
            'en-GB',
        ];
        wrapper.vm.language = { locale: { code: 'de-DE' } };
        expect(wrapper.vm.snippetUpdateState).toBe('builtIn');

        wrapper.vm.language = { locale: { code: 'fr-FR' } };
        wrapper.vm.snippetMetadata = null;
        expect(wrapper.vm.snippetUpdateState).toBe('notAvailable');

        wrapper.vm.snippetMetadata = { locale: 'fr-FR', lastUpdate: null, updateAvailable: false };
        expect(wrapper.vm.snippetUpdateState).toBe('notLinked');

        wrapper.vm.snippetMetadata = { locale: 'fr-FR', lastUpdate: '2026-07-06T22:29:10+00:00', updateAvailable: true };
        expect(wrapper.vm.snippetUpdateState).toBe('updateAvailable');

        wrapper.vm.snippetMetadata = { locale: 'fr-FR', lastUpdate: '2026-07-06T22:29:10+00:00', updateAvailable: false };
        expect(wrapper.vm.snippetUpdateState).toBe('upToDate');

        wrapper.vm.isUpdatingSnippets = true;
        expect(wrapper.vm.snippetUpdateState).toBe('updating');

        wrapper.vm.snippetMetadata = { locale: 'fr-FR', lastUpdate: null, updateAvailable: false };
        expect(wrapper.vm.snippetUpdateState).toBe('linking');
    });

    it('offers the link action for a supported but not linked language', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.language = { locale: { code: 'fr-FR' } };
        wrapper.vm.snippetMetadata = { locale: 'fr-FR', lastUpdate: null, updateAvailable: false };

        expect(wrapper.vm.showSnippetUpdateButton).toBe(true);
        expect(wrapper.vm.showSnippetAutoUpdate).toBe(false);
        expect(wrapper.vm.snippetUpdateButtonLabel).toBe('sw-settings-language.detail.snippetUpdates.linkButton');

        wrapper.vm.isUpdatingSnippets = true;
        expect(wrapper.vm.snippetUpdateButtonLabel).toBe('sw-settings-language.detail.snippetUpdates.linkingButton');
    });

    it('installs the snippets for the current language and reloads the metadata', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.language = { locale: { code: 'fr-FR' } };

        await wrapper.vm.onUpdateSnippets();
        await flushPromises();

        expect(wrapper.vm.translationService.install).toHaveBeenCalledWith({ locales: ['fr-FR'], activate: true });
        expect(wrapper.vm.translationService.getList).toHaveBeenCalled();
        expect(wrapper.vm.isUpdatingSnippets).toBe(false);
    });

    it('should collapse the assigned sales channels to three and expose the count in the card title', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const salesChannel = (id, name) => ({ id, name, translated: { name }, type: { iconName: 'regular-shop' } });
        wrapper.vm.language = {
            name: 'Finnish',
            active: true,
            salesChannels: [
                salesChannel('1', 'A'),
                salesChannel('2', 'B'),
                salesChannel('3', 'C'),
                salesChannel('4', 'D'),
            ],
        };

        expect(wrapper.vm.assignedSalesChannels).toHaveLength(4);
        expect(wrapper.vm.visibleSalesChannels).toHaveLength(3);
        expect(wrapper.vm.salesChannelsCardTitle).toContain('(4)');

        wrapper.vm.showAllSalesChannels = true;

        expect(wrapper.vm.visibleSalesChannels).toHaveLength(4);
    });

    it('requests the assigned sales channels sorted alphabetically by name via the criteria', async () => {
        const wrapper = await createWrapper([], 'language-id-1');
        await flushPromises();

        const getMock = wrapper.vm.repositoryFactory.create('language').get;
        const criteria = getMock.mock.calls.find((args) => args[2])?.[2];

        expect(criteria.getAssociation('salesChannels').sortings).toEqual([
            expect.objectContaining({ field: 'name', order: 'ASC' }),
        ]);
    });

    it('hides the sales channel and snippet update cards while creating a new language', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.language = { isNew: () => true, locale: { code: 'de-DE' } };
        await flushPromises();

        expect(wrapper.vm.isNewLanguage).toBe(true);
        expect(wrapper.find('.sw-settings-language-detail__cards-row').exists()).toBe(false);
    });

    it('shows the sales channel and snippet update cards on an existing language', async () => {
        const wrapper = await createWrapper([], 'language-id-1');
        await flushPromises();

        expect(wrapper.vm.isNewLanguage).toBe(false);
        expect(wrapper.find('.sw-settings-language-detail__cards-row').exists()).toBe(true);
    });
});
