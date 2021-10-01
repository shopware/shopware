import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-import-export/component/sw-import-export-edit-profile-modal';

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
                        mappedKey: 'product_number'
                    }
                ]
            }
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
                mappedKey: 'product_number'
            }
        ],
        config: {
            createEntities: true,
            updateEntities: true
        }
    };

    beforeEach(() => {
        localVue = createLocalVue();

        wrapper = shallowMount(Shopware.Component.build('sw-import-export-edit-profile-modal'), {
            localVue,
            stubs: {
                'sw-select-base': true,
                'sw-button': true,
                'sw-tabs': true,
                'sw-modal': true
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
                            }
                        };
                    }
                },
                importExportProfileMapping: {
                    validate: () => {
                        return {
                            missingRequiredFields: {
                                length: missingRequiredFieldsLength
                            }
                        };
                    },
                    getSystemRequiredFields: () => {
                        return systemRequiredFields;
                    }
                }
            }
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

        wrapper.vm.getParentProfileSelected().then((result) => {
            expect(result).toEqual(mockParentProfiles[0]);
        });
    });

    it('should be null of parentProfile', async () => {
        parentProfileTotal = 0;

        await wrapper.setProps({ profile: mockProfile });

        wrapper.vm.getParentProfileSelected().then((result) => {
            expect(result).toBeNull();
        });
    });

    it('should be null of parentProfile when search was error', async () => {
        searchError = true;

        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.setProps({ profile: mockProfile });

        await wrapper.vm.getParentProfileSelected();

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-import-export.profile.messageSearchParentProfileError'
        });

        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should be save profile fail with missing required fields', async () => {
        missingRequiredFieldsLength = 1;
        searchError = false;

        await wrapper.setProps({ profile: mockProfile });

        await wrapper.vm.saveProfile();

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.missingRequiredFields.length).toBe(1);
    });

    it('should be empty array for missing required fields when run resetViolations', () => {
        wrapper.vm.resetViolations();
        expect(wrapper.vm.missingRequiredFields).toEqual([]);
    });

    it('should be have mapping length data when run mappingLength', () => {
        wrapper.setProps({
            profile: { mapping: { length: 4 } }
        });

        expect(wrapper.vm.mappingLength).toEqual(4);
    });

    it('should be mapping length data is 0 when run mappingLength', () => {
        wrapper.setProps({ profile: {} });

        expect(wrapper.vm.mappingLength).toEqual(0);
    });


    it('should be isNew for profile when profile data is empty', async () => {
        wrapper.setProps({ profile: { isNew: () => {} } });

        expect(wrapper.vm.profile.isNew).toBeTruthy();
    });

    it('should set the updateEntities and createEntities config options', async () => {
        await wrapper.setProps({ profile: mockProfile });
        // create and update should be true from the mockProfile inside the component
        expect(wrapper.vm.profile.config.createEntities).toBeTruthy();
        expect(wrapper.vm.profile.config.updateEntities).toBeTruthy();

        // switch create to false (simulate v-model)
        wrapper.vm.profile.config.createEntities = false;
        // simulate @change event
        wrapper.vm.onCreateEntitiesChanged(wrapper.vm.profile.config.createEntities);
        await wrapper.vm.$nextTick();
        expect(wrapper.vm.profile.config.createEntities).toBeFalsy();

        // also switch update to false (one must stay true -> this should switch create back to true)
        wrapper.vm.profile.config.updateEntities = false;
        wrapper.vm.onUpdateEntitiesChanged(wrapper.vm.profile.config.updateEntities);
        await wrapper.vm.$nextTick();
        expect(wrapper.vm.profile.config.updateEntities).toBeFalsy();
        expect(wrapper.vm.profile.config.createEntities).toBeTruthy();

        // now switch create back to false (which should also switch update back to true)
        wrapper.vm.profile.config.createEntities = false;
        wrapper.vm.onCreateEntitiesChanged(wrapper.vm.profile.config.createEntities);
        await wrapper.vm.$nextTick();
        expect(wrapper.vm.profile.config.updateEntities).toBeTruthy();
    });

    it.each(
        [
            {
                sourceEntity: 'product',
                profileType: null,
                availableEntities: ['product', 'customer', 'order'],
                disabledEntities: [],
                availableProfileTypes: ['import', 'import-export', 'export'],
                disabledProfileTypes: []
            },
            {
                sourceEntity: 'order',
                profileType: null,
                availableEntities: ['product', 'customer', 'order'],
                disabledEntities: [],
                availableProfileTypes: ['export'],
                disabledProfileTypes: ['import', 'import-export']
            },
            {
                sourceEntity: null,
                profileType: 'export',
                availableEntities: ['product', 'customer', 'order'],
                disabledEntities: [],
                availableProfileTypes: ['import', 'import-export', 'export'],
                disabledProfileTypes: []
            },
            {
                sourceEntity: null,
                profileType: 'import',
                availableEntities: ['product', 'customer'],
                disabledEntities: ['order'],
                availableProfileTypes: ['import', 'import-export', 'export'],
                disabledProfileTypes: []
            },
            {
                sourceEntity: 'order',
                profileType: 'export',
                availableEntities: ['product', 'customer', 'order'],
                disabledEntities: [],
                availableProfileTypes: ['export'],
                disabledProfileTypes: ['import', 'import-export']
            }
        ]
    )('should enable disable correct types and entities ', async (data) => {
        global.activeFeatureFlags = ['FEATURE_NEXT_8097'];
        wrapper.setProps({ profile: mockProfile });
        wrapper.vm.profile.sourceEntity = data.sourceEntity;
        wrapper.vm.profile.type = data.profileType;

        data.availableEntities.forEach(entity => {
            const currentEntity = wrapper.vm.supportedEntities.find(item => item.value === entity);
            expect(wrapper.vm.shouldDisableObjectType(currentEntity)).toBeFalsy();
        });
        data.disabledEntities.forEach(entity => {
            const currentEntity = wrapper.vm.supportedEntities.find(item => item.value === entity);
            expect(wrapper.vm.shouldDisableObjectType(currentEntity)).toBeTruthy();
        });
        data.availableProfileTypes.forEach(profileType => {
            expect(wrapper.vm.shouldDisableProfileType({ value: profileType })).toBeFalsy();
        });
        data.disabledProfileTypes.forEach(profileType => {
            expect(wrapper.vm.shouldDisableProfileType({ value: profileType })).toBeTruthy();
        });
    });
});
