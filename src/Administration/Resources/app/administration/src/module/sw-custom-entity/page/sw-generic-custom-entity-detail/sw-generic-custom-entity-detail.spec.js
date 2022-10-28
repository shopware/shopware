import { shallowMount, config } from '@vue/test-utils';

import 'src/module/sw-custom-entity/page/sw-generic-custom-entity-detail';
import 'src/app/component/base/sw-button-process';

const testEntityName = 'custom_test_entity';
const testEntityCreateId = 'new-id';
const testEntityData = {
    id: 'some-id',
    title: 'some-title',
    description: 'some-description',
    swCmsPageId: 'CMS-PAGE-ID-MOCK',
    swSlotConfig: {
        'SLOT-ID-MOCK': 'TEXT-OVERRIDE-MOCK',
    },
    position: 10,
};

const customEntityDefinition = {
    entity: testEntityName,
    properties: {
        id: {
            type: 'string'
        },
        title: {
            type: 'string'
        },
        description: {
            type: 'string'
        },
        position: {
            type: 'int'
        },
    },
    flags: {
        'admin-ui': {
            color: 'some-hex-color',
            detail: {
                tabs: [{
                    name: 'main',
                    cards: [{
                        name: 'general',
                        fields: [{
                            ref: 'title'
                        }, {
                            ref: 'description'
                        }, {
                            ref: 'position'
                        }],
                    }, {
                        name: 'useless',
                        fields: [{
                            ref: 'description',
                        }, {
                            ref: 'position',
                        }],
                    }]
                }, {
                    name: 'secondary',
                    cards: [{
                        name: 'secondary-useless',
                        fields: [{
                            ref: 'position'
                        }],
                    }]
                }]
            }
        },
        'cms-aware': true,
    }
};

const customEntityRepository = {
    create: () => ({
        ...testEntityData,
        id: testEntityCreateId,
    }),
    get: async (id) => {
        if (id === 'some-id') {
            return testEntityData;
        }

        throw new Error(`Mocked entity for id "${id}" not found`);
    },
    save: async (customEntityData) => {
        if (customEntityData?.id) {
            config.mocks.$router.push({
                name: 'sw.custom.entity.detail',
                params: {
                    id: customEntityData.id,
                },
            });
        }
    }
};

async function createWrapper({ activeTab = 'main', routeId = null } = {}) {
    config.mocks.$route = {
        params: {
            entityName: testEntityName,
            id: routeId,
        },
        meta: {
            $module: {
                icon: null,
            },
        },
    };

    return shallowMount(await Shopware.Component.build('sw-generic-custom-entity-detail'), {
        provide: {
            customEntityDefinitionService: {
                getDefinitionByName: () => customEntityDefinition,
            },
            repositoryFactory: {
                create(name) {
                    if (name === 'custom_test_entity') {
                        return customEntityRepository;
                    }

                    throw new Error(`Repository for ${name} is not mocked`);
                }
            },
        },
        stubs: {
            'sw-page': {
                template: '<div class="sw-page"><slot name="search-bar"/><slot name="smart-bar-header" /><slot name="smart-bar-actions"/><slot name="language-switch" /><slot name="content"/></div>',
            },
            'sw-search-bar': {
                template: '<div class="sw-search-bar"></div>',
                props: [
                    'initial-search-type',
                    'initial-search'
                ]
            },
            'sw-card-view': true,
            'sw-card': true,
            'sw-tabs': {
                template: `<div class="sw-tabs"><slot></slot><slot name="content" active="${activeTab}"></slot></div>`,
            },
            'sw-tabs-item': true,
            'sw-button-process': {
                template: '<div class="sw-button-process" @click="$emit(`click`)"></div>',
            },
            'sw-button': {
                template: '<button></button>',
            },
            'sw-language-switch': {
                template: '<div class="sw-language-switch"></div>'
            },
            'sw-custom-entity-input-field': {
                template: '<input/>',
            },
            'sw-generic-cms-page-assignment': {
                template: '<div class="sw-generic-cms-page-assignment"></div>',
                props: ['cms-page-id', 'slot-overrides'],
            }
        }
    });
}

const numberOfElementsDataProvider = [{
    activeTab: 'main',
    cardCount: 2,
    cards: [{
        name: 'general',
        fieldCount: 3,
        fields: [{
            ref: 'title',
        }, {
            ref: 'description',
        }, {
            ref: 'position',
        }],
    }, {
        name: 'useless',
        fieldCount: 2,
        fields: [{
            ref: 'description',
        }, {
            ref: 'position',
        }],
    }]
}, {
    activeTab: 'secondary',
    cardCount: 1,
    cards: [{
        name: 'secondary-useless',
        fieldCount: 1,
        fields: [{
            ref: 'position',
        }],
    }]
}];

describe('module/sw-custom-entity/page/sw-generic-custom-entity-detail', () => {
    it('should render the correct number of tabs, tab-items and activeTabs with correct labels', async () => {
        const wrapper = await createWrapper();

        // Check 2 tab-items and tabs, one of them visible
        const tabItems = wrapper.findAll('.sw-generic-custom-entity-detail__tab-item');
        expect(tabItems.length).toEqual(3);
        expect(tabItems.at(0).text()).toBe('custom_test_entity.tabs.main');
        expect(tabItems.at(1).text()).toBe('custom_test_entity.tabs.secondary');
        expect(wrapper.findAll('.sw-generic-custom-entity-detail__tab').length).toBe(1);
    });

    numberOfElementsDataProvider.forEach((data) => {
        it(`should render the correct number of cards and fields [activeTab="${data.activeTab}"]`, async () => {
            const wrapper = await createWrapper({ activeTab: data.activeTab });

            const cardElements = wrapper.findAll('.sw-generic-custom-entity-detail__card');
            expect(cardElements.length).toBe(data.cardCount);

            // Check title and amount of children in each card
            data.cards.forEach((card, cardIndex) => {
                expect(cardElements.at(cardIndex).attributes().title)
                    .toBe(`custom_test_entity.cards.${card.name}`);

                const fieldElements = cardElements.at(cardIndex).findAll('.sw-generic-custom-entity-detail__field');
                expect(fieldElements.length).toEqual(card.fieldCount);

                // Check title, placeholder & helpText of each field
                card.fields.forEach((field, fieldIndex) => {
                    const currentAttributes = fieldElements.at(fieldIndex).attributes();
                    expect(currentAttributes.label).toBe(`custom_test_entity.fields.${field.ref}`);
                    expect(currentAttributes.placeholder).toBe(`custom_test_entity.fields.${field.ref}Placeholder`);
                    expect(currentAttributes['help-text']).toBe(`custom_test_entity.fields.${field.ref}HelpText`);
                });
            });
        });
    });

    it('should create a new Entity, when no ID is given and be pushed to a detail page on save', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.customEntityDataId).toBe(null);
        expect(wrapper.vm.customEntityData.id).toBe(testEntityCreateId);

        await wrapper.get('.sw-generic-custom-entity-detail__save-action').trigger('click');
        await flushPromises();

        expect(config.mocks.$router.push).toHaveBeenCalledWith({
            name: 'sw.custom.entity.detail',
            params: {
                id: testEntityCreateId,
            }
        });
    });

    it('should create a new Entity, when an ID is given via $route', async () => {
        const wrapper = await createWrapper({
            activeTab: 'main',
            routeId: testEntityData.id
        });

        expect(wrapper.vm.customEntityDataId).toBe(testEntityData.id);

        await flushPromises();

        expect(wrapper.vm.customEntityData.id).toBe(testEntityData.id);
        expect(wrapper.vm.customEntityData.title).toBe(testEntityData.title);
        expect(wrapper.vm.customEntityData.description).toBe(testEntityData.description);
    });

    it('should render the layout tab, pass in the cmsPageId and the cmsSlotOverrides and react to changes', async () => {
        const wrapper = await createWrapper({
            activeTab: 'cms-aware-tab',
            routeId: testEntityData.id
        });
        await flushPromises();

        const cmsAwareTab = wrapper.find('.sw-generic-custom-entity-detail__tab-cms-aware');
        expect(cmsAwareTab.props('cmsPageId')).toBe(testEntityData.swCmsPageId);
        expect(cmsAwareTab.props('slotOverrides')).toEqual(testEntityData.swSlotConfig);

        const mockCMSPageId = 'mockCMSPageId';
        const mockSlotOverrides = 'mockSlotOverride';

        cmsAwareTab.vm.$emit('update:cms-page-id', mockCMSPageId);
        await flushPromises();

        cmsAwareTab.vm.$emit('update:slot-overrides', mockSlotOverrides);
        await flushPromises();

        expect(wrapper.vm.customEntityData.swCmsPageId).toBe(mockCMSPageId);
        expect(wrapper.vm.customEntityData.swSlotConfig).toBe(mockSlotOverrides);
    });

    it('should create a new layout on the `create-layout` event', async () => {
        const wrapper = await createWrapper({
            activeTab: 'cms-aware-tab',
            routeId: testEntityData.id
        });
        await flushPromises();
        await flushPromises();

        const cmsAwareTab = wrapper.find('.sw-generic-custom-entity-detail__tab-cms-aware');
        cmsAwareTab.vm.$emit('create-layout');
        await flushPromises();

        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({
            name: 'sw.cms.create',
            params: {
                id: testEntityData.id,
                type: testEntityName,
            }
        });
    });
});
