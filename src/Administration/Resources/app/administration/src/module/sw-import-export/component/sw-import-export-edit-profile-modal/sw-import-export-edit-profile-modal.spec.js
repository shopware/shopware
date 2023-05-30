/**
 * @package system-settings
 */
import { createLocalVue, shallowMount } from '@vue/test-utils';
import swImportExportEditProfileModal from 'src/module/sw-import-export/component/sw-import-export-edit-profile-modal';

Shopware.Component.register('sw-import-export-edit-profile-modal', swImportExportEditProfileModal);

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

describe('module/sw-import-export/components/sw-import-export-edit-profile-modal', () => {
    let wrapper;
    let localVue;
    let missingRequiredFieldsLength;
    const systemRequiredFields = {};
    let parentProfileTotal = 1;
    let searchError = false;

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

    beforeEach(async () => {
        localVue = createLocalVue();

        wrapper = shallowMount(await Shopware.Component.build('sw-import-export-edit-profile-modal'), {
            localVue,
            stubs: {
                'sw-select-base': true,
                'sw-button': true,
                'sw-tabs': true,
                'sw-modal': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => {
                                if (searchError) {
                                    return Promise.reject();
                                }

                                return Promise.resolve(getMockParentProfiles(parentProfileTotal));
                            },
                        };
                    },
                },
                importExportProfileMapping: {
                    validate: () => {
                        return {
                            missingRequiredFields: {
                                length: missingRequiredFieldsLength,
                            },
                        };
                    },
                    getSystemRequiredFields: () => {
                        return systemRequiredFields;
                    },
                },
                importExportUpdateByMapping: {
                    removeUnusedMappings: () => {
                    },
                },
            },
        });
    });

    afterEach(() => {
        localVue = null;
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should be save profile success', async () => {
        missingRequiredFieldsLength = 0;

        await wrapper.setProps({ profile: mockProfile });

        await wrapper.vm.saveProfile();

        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('profile-save')).toBeTruthy();
    });

    it('should be get parent of profile', async () => {
        await wrapper.setProps({ profile: mockProfile });

        const mockParentProfiles = getMockParentProfiles();

        expect((await wrapper.vm.getParentProfileSelected())).toEqual(mockParentProfiles[0]);
    });

    it('should be null of parentProfile', async () => {
        parentProfileTotal = 0;

        await wrapper.setProps({ profile: mockProfile });

        expect((await wrapper.vm.getParentProfileSelected())).toBeNull();
    });

    it('should be null of parentProfile when search was error', async () => {
        searchError = true;

        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.setProps({ profile: mockProfile });

        await wrapper.vm.getParentProfileSelected();

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-import-export.profile.messageSearchParentProfileError',
        });

        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should be save profile fail with missing required fields', async () => {
        missingRequiredFieldsLength = 1;
        searchError = false;

        await wrapper.setProps({ profile: mockProfile });

        await wrapper.vm.saveProfile();

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.missingRequiredFields).toHaveLength(1);
    });

    it('should be empty array for missing required fields when run resetViolations', async () => {
        wrapper.vm.resetViolations();
        expect(wrapper.vm.missingRequiredFields).toEqual([]);
    });

    it('should be isNew for profile when profile data is empty', async () => {
        await wrapper.setProps({ profile: { isNew: () => {} } });

        expect(wrapper.vm.profile.isNew).toBeTruthy();
    });

    it('should set the updateEntities and createEntities config options', async () => {
        await wrapper.setProps({ profile: mockProfile });
        // create and update should be true from the mockProfile inside the component
        expect(wrapper.vm.profile.config.createEntities).toBeTruthy();
        expect(wrapper.vm.profile.config.updateEntities).toBeTruthy();
    });
});
