/**
 * @sw-package fundamentals@after-sales
 */
import { mount } from '@vue/test-utils';
import ImportExportProfileMappingService from '../../service/importExportProfileMapping.service';

function getMockParentProfiles(total = 1) {
    let mockParentProfiles = [];

    if (total > 0) {
        mockParentProfiles = [
            {
                name: 'Product profile',
                mapping: [
                    {
                        id: 'b36961c5f32c4f4d9e17ed9718f5fca2',
                        key: 'productNumber',
                        mappedKey: 'product_number',
                    },
                ],
            },
        ];
    }

    mockParentProfiles.total = total;

    return mockParentProfiles;
}

const mockProfile = {
    sourceEntity: 'product',
    type: 'import-export',
    mapping: [
        {
            id: 'b36961c5f32c4f4d9e17ed9718f5fca2',
            key: 'productNumber',
            mappedKey: 'product_number',
        },
    ],
    config: {
        createEntities: true,
        updateEntities: true,
    },
};

const defaultProps = {
    profile: mockProfile,
    show: true,
};

async function createWrapper(
    props = defaultProps,
    params = {
        searchError: false,
        parentProfileTotal: 1,
        missingRequiredFieldsLength: 0,
        systemRequiredFields: {},
    },
) {
    return mount(
        await wrapTestComponent('sw-import-export-edit-profile-modal', {
            sync: true,
        }),
        {
            props,
            global: {
                stubs: {
                    'sw-select-base': true,
                    'sw-tabs': true,
                    'sw-tabs-item': true,
                    'sw-button': await wrapTestComponent('sw-button'),
                    'sw-modal': await wrapTestComponent('sw-modal'),
                    'sw-import-export-edit-profile-general': true,
                    'sw-import-export-edit-profile-field-indicators': true,
                    'sw-import-export-edit-profile-import-settings': true,
                    'sw-import-export-edit-profile-modal-mapping': true,
                    'sw-import-export-edit-profile-modal-identifiers': true,
                },
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: () => {
                                    if (params.searchError) {
                                        return Promise.reject();
                                    }

                                    return Promise.resolve(getMockParentProfiles(params.parentProfileTotal));
                                },
                            };
                        },
                    },
                    importExportProfileMapping: new ImportExportProfileMappingService(Shopware.EntityDefinition),
                    importExportUpdateByMapping: {
                        removeUnusedMappings: () => {},
                    },
                    shortcutService: {
                        startEventListener: () => {},
                        stopEventListener: () => {},
                    },
                },
            },
        },
    );
}

describe('module/sw-import-export/components/sw-import-export-edit-profile-modal', () => {
    it('should save profile successful', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.saveProfile();
        await flushPromises();

        expect(wrapper.emitted('profile-save')).toBeTruthy();
    });

    it('should be get parent of profile', async () => {
        const wrapper = await createWrapper();

        const mockParentProfiles = getMockParentProfiles();

        expect(await wrapper.vm.getParentProfileSelected()).toEqual(mockParentProfiles[0]);
    });

    it('should be null of parentProfile', async () => {
        const wrapper = await createWrapper(defaultProps, {
            searchError: false,
            parentProfileTotal: 0,
            missingRequiredFieldsLength: 0,
            systemRequiredFields: {},
        });

        expect(await wrapper.vm.getParentProfileSelected()).toBeNull();
    });

    it('should be null of parentProfile when search was error', async () => {
        const wrapper = await createWrapper(defaultProps, {
            searchError: true,
            parentProfileTotal: 1,
            missingRequiredFieldsLength: 0,
            systemRequiredFields: {},
        });

        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.getParentProfileSelected();
        await flushPromises();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-import-export.profile.messageSearchParentProfileError',
        });

        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should be save profile fail with missing required fields', async () => {
        const wrapper = await createWrapper(
            {
                ...defaultProps,
                profile: {
                    ...mockProfile,
                    mapping: [],
                },
            },
            {
                searchError: false,
                parentProfileTotal: 1,
                missingRequiredFieldsLength: 1,
                systemRequiredFields: {},
            },
        );

        await wrapper.vm.saveProfile();
        await flushPromises();

        expect(wrapper.vm.missingRequiredFields).toHaveLength(1);
    });

    it('should be empty array for missing required fields when run resetViolations', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.resetViolations();
        expect(wrapper.vm.missingRequiredFields).toEqual([]);
        expect(wrapper.vm.duplicateMappings).toEqual([]);
    });

    it('should be isNew for profile when profile data is empty', async () => {
        const wrapper = await createWrapper({
            ...defaultProps,
            profile: {
                ...mockProfile,
                isNew: () => {},
            },
        });

        expect(wrapper.vm.profile.isNew).toBeTruthy();
    });

    it('should set the updateEntities and createEntities config options', async () => {
        const wrapper = await createWrapper();

        // create and update should be true from the mockProfile inside the component
        expect(wrapper.vm.profile.config.createEntities).toBeTruthy();
        expect(wrapper.vm.profile.config.updateEntities).toBeTruthy();
    });

    it('should open violations modal if duplicate is found and clear if modal is closed', async () => {
        const modalSelector = { name: 'sw-modal__wrapped' };

        const wrapper = await createWrapper({
            ...defaultProps,
            profile: {
                ...mockProfile,
                mapping: [
                    ...mockProfile.mapping,
                    {
                        id: Shopware.Utils.createId(),
                        key: 'id',
                        mappedKey: 'id_1',
                    },
                    {
                        id: Shopware.Utils.createId(),
                        key: 'id',
                        mappedKey: 'id_2',
                    },
                ],
            },
        });

        await flushPromises();

        const mappingModal = wrapper.findComponent(modalSelector);

        await mappingModal.find('.mt-button--primary').trigger('click');
        await flushPromises();

        expect(wrapper.vm.duplicateMappings).toHaveLength(1);
        expect(wrapper.emitted('profile-save')).toBeUndefined();

        expect(wrapper.findAllComponents(modalSelector)).toHaveLength(2);
        const violationModal = wrapper.findAllComponents(modalSelector).at(1);

        expect(violationModal.find('.sw-import-export-edit-profile-modal__violation-modal-duplicate-mapping').exists()).toBe(
            true,
        );

        await violationModal.find('.mt-button--secondary').trigger('click');

        expect(wrapper.findAllComponents(modalSelector)).toHaveLength(1);
        await flushPromises();

        expect(wrapper.vm.duplicateMappings).toStrictEqual([]);
    });
});
