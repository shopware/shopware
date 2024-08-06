/**
 * @package services-settings
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';

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

async function createWrapper(params = { searchError: false, parentProfileTotal: 1, missingRequiredFieldsLength: 0, systemRequiredFields: {} }) {
    return mount(await wrapTestComponent('sw-import-export-edit-profile-modal', { sync: true }), {
        global: {
            stubs: {
                'sw-select-base': true,
                'sw-button': true,
                'sw-tabs': true,
                'sw-tabs-item': true,
                'sw-modal': true,
                'sw-alert': true,
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
                importExportProfileMapping: {
                    validate: () => {
                        return {
                            missingRequiredFields: {
                                length: params.missingRequiredFieldsLength,
                            },
                        };
                    },
                    getSystemRequiredFields: () => {
                        return params.systemRequiredFields;
                    },
                },
                importExportUpdateByMapping: {
                    removeUnusedMappings: () => {
                    },
                },
            },
        },
    });
}

describe('module/sw-import-export/components/sw-import-export-edit-profile-modal', () => {
    let wrapper;

    it('should be save profile success', async () => {
        wrapper = await createWrapper();
        await wrapper.setProps({ profile: mockProfile });

        await wrapper.vm.saveProfile();
        await flushPromises();

        expect(wrapper.emitted('profile-save')).toBeTruthy();
    });

    it('should be get parent of profile', async () => {
        wrapper = await createWrapper();
        await wrapper.setProps({ profile: mockProfile });

        const mockParentProfiles = getMockParentProfiles();

        expect((await wrapper.vm.getParentProfileSelected())).toEqual(mockParentProfiles[0]);
    });

    it('should be null of parentProfile', async () => {
        wrapper = await createWrapper({ searchError: false, parentProfileTotal: 0, missingRequiredFieldsLength: 0, systemRequiredFields: {} });
        await wrapper.setProps({ profile: mockProfile });

        expect((await wrapper.vm.getParentProfileSelected())).toBeNull();
    });

    it('should be null of parentProfile when search was error', async () => {
        wrapper = await createWrapper({ searchError: true, parentProfileTotal: 1, missingRequiredFieldsLength: 0, systemRequiredFields: {} });

        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.setProps({ profile: mockProfile });

        await wrapper.vm.getParentProfileSelected();
        await flushPromises();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-import-export.profile.messageSearchParentProfileError',
        });

        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should be save profile fail with missing required fields', async () => {
        wrapper = await createWrapper({ searchError: false, parentProfileTotal: 1, missingRequiredFieldsLength: 1, systemRequiredFields: {} });
        await wrapper.setProps({ profile: mockProfile });

        await wrapper.vm.saveProfile();
        await flushPromises();

        expect(wrapper.vm.missingRequiredFields).toHaveLength(1);
    });

    it('should be empty array for missing required fields when run resetViolations', async () => {
        wrapper.vm.resetViolations();
        expect(wrapper.vm.missingRequiredFields).toEqual([]);
    });

    it('should be isNew for profile when profile data is empty', async () => {
        wrapper = await createWrapper();
        await wrapper.setProps({ profile: { isNew: () => {} } });

        expect(wrapper.vm.profile.isNew).toBeTruthy();
    });

    it('should set the updateEntities and createEntities config options', async () => {
        wrapper = await createWrapper();
        await wrapper.setProps({ profile: mockProfile });
        // create and update should be true from the mockProfile inside the component
        expect(wrapper.vm.profile.config.createEntities).toBeTruthy();
        expect(wrapper.vm.profile.config.updateEntities).toBeTruthy();
    });
});
