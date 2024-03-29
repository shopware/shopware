/**
 * @package services-settings
 */
import { mount } from '@vue/test-utils';
import ImportExportService from 'src/module/sw-import-export/service/importExport.service';
import EntityCollection from '../../../../core/data/entity-collection.data';
import uuid from '../../../../../test/_helper_/uuid';

function importExportProfiles() {
    return new EntityCollection(
        '/import_export_profile',
        'category',
        null,
        { isShopwareContext: true },
        [
            {
                id: uuid.get('profile-0'),
                name: 'profile-0',
                config: {},
                translated: {
                    label: 'profile-0-label',
                },
            },
            {
                id: uuid.get('profile-1'),
                name: 'profile-1',
                config: {},
                translated: {
                    label: 'profile-1-label',
                },
            },
        ],
        2,
        null,
    );
}

async function createWrapper(profiles = null) {
    return mount(await wrapTestComponent('sw-import-export-view-profiles', { sync: true }), {
        global: {
            stubs: {
                'sw-card': await wrapTestComponent('sw-card'),
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-simple-search-field': true,
                'sw-entity-listing': await wrapTestComponent('sw-entity-listing'),
                'sw-import-export-edit-profile-modal': {
                    template: `
                        <div class="sw-import-export-edit-profile-modal"></div>
                    `,
                },
                'sw-import-export-new-profile-wizard': {
                    template: `
                        <div class="sw-import-export-new-profile-wizard"></div>
                    `,
                },
                'sw-modal': await wrapTestComponent('sw-modal'),
            },
            provide: {
                importExport: new ImportExportService(),
                repositoryFactory: {
                    create() {
                        return {
                            search() {
                                return profiles;
                            },
                            create() {
                                return Promise.resolve();
                            },
                            get(id) {
                                return profiles.get(id);
                            },
                            delete(id) {
                                profiles.remove(id);
                                return Promise.resolve();
                            },
                        };
                    },
                },
                shortcutService: {
                    stopEventListener: () => {},
                    startEventListener: () => {},
                },
            },
        },
    });
}

describe('src/module/sw-extension/component/sw-extension-card-base', () => {
    let wrapper;

    const responses = global.repositoryFactoryMock.responses;

    beforeEach(async () => {
        responses.addResponse({
            method: 'Post',
            url: '/search/import-export-profile',
            status: 200,
            response: { data: [] },
        });
    });

    it('should open the new profile wizard when creating a new profile', async () => {
        wrapper = await createWrapper(importExportProfiles());

        expect(wrapper.find('.sw-import-export-new-profile-wizard').exists()).toBe(false);

        const createProfileButton = wrapper.find('.sw-import-export-view-profiles__create-action');
        await createProfileButton.trigger('click');

        expect(wrapper.find('.sw-import-export-new-profile-wizard').exists()).toBe(true);
    });

    it('should open the edit modal when editing a profile', async () => {
        wrapper = await createWrapper(importExportProfiles());
        await flushPromises();

        const editProfileModal = wrapper.find('.sw-import-export-edit-profile-modal');

        expect(editProfileModal.exists()).toBe(true);
        expect(editProfileModal.attributes('show')).toBeUndefined();

        const createProfileButton = wrapper.find('.sw-data-grid__row--0 .sw-import-export-view-profiles__listing-open-action');
        await createProfileButton.trigger('click');
        await flushPromises();

        expect(editProfileModal.attributes('show')).toBe('true');
    });

    it('should delete a profile', async () => {
        wrapper = await createWrapper(importExportProfiles());
        await flushPromises();

        expect(wrapper.vm.profiles).toHaveLength(2);

        const createProfileButton = wrapper.find('.sw-data-grid__row--0 .sw-import-export-view-profiles__listing-delete-action');
        await createProfileButton.trigger('click');
        await flushPromises();

        document.body.querySelector('.sw-button--danger').click();
        await flushPromises();

        expect(wrapper.vm.profiles).toHaveLength(1);
    });
});
