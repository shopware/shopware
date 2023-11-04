import { shallowMount } from '@vue/test-utils';
import swCategoryDetailCustomEntity from 'src/module/sw-category/view/sw-category-detail-custom-entity/index';

Shopware.Component.register('sw-category-detail-custom-entity', swCategoryDetailCustomEntity);

const customEntity1 = {
    id: 'CUSTOM_ENTITY_ID_1',
    name: 'CUSTOM_ENTITY_NAME_1',
    instanceRepository: ['CUSTOM_ENTITY_INSTANCES_1'],
};

const customEntity2 = {
    id: 'CUSTOM_ENTITY_ID_2',
    name: 'CUSTOM_ENTITY_NAME_2',
    instanceRepository: ['CUSTOM_ENTITY_INSTANCES_2'],
};

const emptyEntityCollection = ['EMPTY_ENTITY_COLLECTION'];

const customEntityRepositoryMock = {
    get: (id) => {
        if (id === customEntity1.id) {
            return Promise.resolve({
                id: customEntity1.id,
                name: customEntity1.name,
            });
        }

        if (id === customEntity2.id) {
            return Promise.resolve({
                id: customEntity2.id,
                name: customEntity2.name,
            });
        }

        return Promise.resolve(null);
    },
};

async function createWrapper() {
    if (Shopware.State.get('swCategoryDetail')) {
        Shopware.State.unregisterModule('swCategoryDetail');
    }

    Shopware.State.registerModule('swCategoryDetail', {
        namespaced: true,
        state: {
            category: {
                isNew: () => false,
                customEntityTypeId: customEntity1.id,
                extensions: {
                    customEntityName1SwCategories: customEntity1.instanceRepository,
                    customEntityName2SwCategories: customEntity2.instanceRepository,
                },
            },
        },
    });

    return shallowMount(await Shopware.Component.build('sw-category-detail-custom-entity'), {
        stubs: {
            'sw-card': {
                template: '<div class="sw-card"><slot /></div>',
                props: ['title', 'position-identifier'],
            },
            'sw-entity-single-select': {
                template: '<div class="sw-entity-single-select"></div>',
                props: ['value', 'label', 'help-text', 'disabled', 'criteria', 'entity', 'required'],
            },
            'sw-many-to-many-assignment-card': {
                template: '<div class="sw-many-to-many-assignment-card"><slot name="prepend-select" /><slot name="empty-state" /></div>',
                props: ['entityCollection', 'title', 'columns', 'local-mode', 'label-property', 'criteria', 'select-label', 'placeholder'],
                model: {
                    prop: 'entityCollection',
                    event: 'change',
                },
            },
            'sw-empty-state': {
                template: '<div class="sw-empty-state"></div>',
                props: ['title', 'absolute'],
            },
        },
        provide: {
            repositoryFactory: {
                create: (repositoryName) => {
                    switch (repositoryName) {
                        case 'custom_entity':
                            return customEntityRepositoryMock;
                        default:
                            throw new Error(`No Mock for ${repositoryName} Repository not found`);
                    }
                },
            },
        },
    });
}

/**
 * @package content
 */
describe('src/module/sw-category/view/sw-category-detail-custom-entity/index.ts', () => {
    it('should allow selecting a custom entity', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper();
        wrapper.vm.onEntityChange(undefined);

        await flushPromises();

        // check initial state without custom entity type selected
        expect(wrapper.find('.sw-category-detail-custom-entity__selection-container').props()).toStrictEqual({
            positionIdentifier: 'category-detail-custom-entity',
            title: 'sw-category.base.customEntity.cardTitle',
        });

        const entitySelect = wrapper.find('.sw-entity-single-select');
        expect(entitySelect.props()).toStrictEqual({
            value: undefined,
            label: 'sw-category.base.customEntity.assignment.label',
            helpText: 'sw-category.base.customEntity.assignment.helpText',
            disabled: false,
            criteria: expect.objectContaining({
                filters: [{
                    field: 'flags',
                    type: 'contains',
                    value: 'cms-aware',
                }],
            }),
            entity: 'custom_entity',
            required: '',
        });

        // select a custom entity type
        entitySelect.vm.$emit('change', customEntity1.id, { name: customEntity1.name });
        await flushPromises();
        await wrapper.vm.$nextTick();

        // expect the custom entity type and the customEntityAssignments to have been updated
        expect(wrapper.vm.category.customEntityTypeId).toBe(customEntity1.id);
        expect(wrapper.vm.customEntityAssignments).toBe(customEntity1.instanceRepository);

        expect(wrapper.find('.sw-category-detail-custom-entity__selection-container').exists()).toBe(false);
        expect(wrapper.find('.sw-many-to-many-assignment-card').props()).toStrictEqual({
            columns: [{
                dataIndex: 'cmsAwareTitle',
                label: 'sw-category.base.customEntity.instanceAssignment.title',
                property: 'cmsAwareTitle',
            }],
            criteria: expect.objectContaining({
                sortings: [{
                    field: 'cmsAwareTitle',
                    naturalSorting: false,
                    order: 'ASC',
                }],
            }),
            entityCollection: customEntity1.instanceRepository,
            labelProperty: 'cmsAwareTitle',
            localMode: false,
            placeholder: 'sw-category.base.customEntity.instanceAssignment.placeholder',
            selectLabel: 'sw-category.base.customEntity.instanceAssignment.label',
            title: 'sw-category.base.customEntity.cardTitle',
        });
    });

    it('should allow selecting a custom entity instances', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper();
        await flushPromises();

        // expect a custom entity type to be selected
        expect(wrapper.find('.sw-category-detail-custom-entity__selection-container').exists()).toBe(false);

        expect(wrapper.get('.sw-entity-single-select').props()).toStrictEqual({
            value: customEntity1.id,
            label: 'sw-category.base.customEntity.assignment.label',
            helpText: 'sw-category.base.customEntity.assignment.helpText',
            disabled: false,
            criteria: expect.objectContaining({
                filters: [{
                    field: 'flags',
                    type: 'contains',
                    value: 'cms-aware',
                }],
            }),
            entity: 'custom_entity',
            required: '',
        });

        expect(wrapper.get('.sw-many-to-many-assignment-card').props()).toStrictEqual({
            columns: [{
                dataIndex: 'cmsAwareTitle',
                label: 'sw-category.base.customEntity.instanceAssignment.title',
                property: 'cmsAwareTitle',
            }],
            criteria: expect.objectContaining({
                sortings: [{
                    field: 'cmsAwareTitle',
                    naturalSorting: false,
                    order: 'ASC',
                }],
            }),
            entityCollection: customEntity1.instanceRepository,
            labelProperty: 'cmsAwareTitle',
            localMode: false,
            placeholder: 'sw-category.base.customEntity.instanceAssignment.placeholder',
            selectLabel: 'sw-category.base.customEntity.instanceAssignment.label',
            title: 'sw-category.base.customEntity.cardTitle',
        });

        // select another custom entity type
        wrapper.get('.sw-entity-single-select').vm.$emit('change', customEntity2.id, { name: customEntity2.name });
        await flushPromises();

        expect(wrapper.get('.sw-entity-single-select').props()).toStrictEqual({
            value: customEntity2.id,
            label: 'sw-category.base.customEntity.assignment.label',
            helpText: 'sw-category.base.customEntity.assignment.helpText',
            disabled: false,
            criteria: expect.objectContaining({
                filters: [{
                    field: 'flags',
                    type: 'contains',
                    value: 'cms-aware',
                }],
            }),
            entity: 'custom_entity',
            required: '',
        });

        expect(wrapper.get('.sw-many-to-many-assignment-card').props()).toStrictEqual({
            columns: [{
                dataIndex: 'cmsAwareTitle',
                label: 'sw-category.base.customEntity.instanceAssignment.title',
                property: 'cmsAwareTitle',
            }],
            criteria: expect.objectContaining({
                sortings: [{
                    field: 'cmsAwareTitle',
                    naturalSorting: false,
                    order: 'ASC',
                }],
            }),
            entityCollection: customEntity2.instanceRepository,
            labelProperty: 'cmsAwareTitle',
            localMode: false,
            placeholder: 'sw-category.base.customEntity.instanceAssignment.placeholder',
            selectLabel: 'sw-category.base.customEntity.instanceAssignment.label',
            title: 'sw-category.base.customEntity.cardTitle',
        });

        // trigger a change event
        wrapper.get('.sw-many-to-many-assignment-card').vm.$emit('change', emptyEntityCollection);
        await flushPromises();

        expect(wrapper.get('.sw-many-to-many-assignment-card').props()).toStrictEqual({
            columns: [{
                dataIndex: 'cmsAwareTitle',
                label: 'sw-category.base.customEntity.instanceAssignment.title',
                property: 'cmsAwareTitle',
            }],
            criteria: expect.objectContaining({
                sortings: [{
                    field: 'cmsAwareTitle',
                    naturalSorting: false,
                    order: 'ASC',
                }],
            }),
            entityCollection: emptyEntityCollection,
            labelProperty: 'cmsAwareTitle',
            localMode: false,
            placeholder: 'sw-category.base.customEntity.instanceAssignment.placeholder',
            selectLabel: 'sw-category.base.customEntity.instanceAssignment.label',
            title: 'sw-category.base.customEntity.cardTitle',
        });
    });
});
