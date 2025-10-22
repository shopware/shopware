/**
 * @sw-package fundamentals@after-sales
 */
import { mount } from '@vue/test-utils';
import ImportExportService from 'src/module/sw-import-export/service/importExport.service';
import Entity from 'src/core/data/entity.data';
import EntityCollection from '../../../../core/data/entity-collection.data';
import uuid from '../../../../../test/_helper_/uuid';

global.repositoryFactoryMock.responses.addResponse({
    method: 'Post',
    url: '/search/import-export-profile',
    status: 200,
    response: { data: [] },
});

const importExportProfileFixture = new EntityCollection(
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

const repositoryMock = {
    search: () => Promise.resolve(importExportProfileFixture),
    create: jest.fn(() => new Entity(uuid.get('new-profile'), 'import_export_profile', {})),
    get: (id) => Promise.resolve(importExportProfileFixture.get(id)),
    delete: (id) => Promise.resolve(importExportProfileFixture.remove(id)),
};

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-import-export-view-profiles', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-import-export-new-profile-wizard': await wrapTestComponent('sw-import-export-new-profile-wizard'),
                    'sw-entity-listing': await wrapTestComponent('sw-entity-listing'),
                    'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item'),
                    'sw-modal': await wrapTestComponent('sw-modal'),
                    'sw-import-export-new-profile-wizard-general-page': true,
                    'sw-import-export-new-profile-wizard-csv-page': true,
                    'sw-import-export-new-profile-wizard-mapping-page': true,
                    'sw-wizard': true,
                    'sw-wizard-page': true,
                    'sw-wizard-dot-navigation': true,
                    'sw-simple-search-field': true,
                    'sw-import-export-edit-profile-modal': {
                        template: `
                        <div class="sw-import-export-edit-profile-modal"></div>
                    `,
                    },
                    'sw-extension-component-section': true,
                    'sw-ai-copilot-badge': true,
                    'sw-context-button': {
                        template: '<div><slot></slot></div>',
                    },
                    'sw-loader': true,
                    'router-link': true,
                    'sw-bulk-edit-modal': true,
                    'sw-checkbox-field': true,
                    'sw-data-grid-settings': true,
                    'sw-data-grid-column-boolean': true,
                    'sw-data-grid-inline-edit': true,
                    'sw-data-grid-skeleton': true,
                    'sw-pagination': true,
                    'sw-provide': true,
                },
                provide: {
                    importExportProfileMapping: {},
                    importExport: new ImportExportService(),
                    repositoryFactory: {
                        create: () => repositoryMock,
                    },
                    shortcutService: {
                        stopEventListener: () => {},
                        startEventListener: () => {},
                    },
                },
            },
        },
    );
}

describe('src/module/sw-extension/component/sw-extension-card-base', () => {
    it('should open the new profile wizard when creating a new profile', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-import-export-view-profiles__create-action').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-import-export-new-profile-wizard').exists()).toBe(true);
        expect(repositoryMock.create).toHaveBeenNthCalledWith(1);
        expect(wrapper.vm.selectedProfile.getEntityName()).toBe('import_export_profile');
    });

    it('should open the edit modal when editing a profile', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const editProfileModal = wrapper.find('.sw-import-export-edit-profile-modal');

        expect(editProfileModal.exists()).toBe(true);
        const showEditProfileModal = editProfileModal.attributes('show');
        expect(showEditProfileModal === 'false' || showEditProfileModal === undefined).toBe(true);

        const createProfileButton = wrapper.find(
            '.sw-data-grid__row--0 .sw-import-export-view-profiles__listing-open-action',
        );
        await createProfileButton.trigger('click');
        await flushPromises();

        expect(editProfileModal.attributes('show')).toBe('true');
    });

    it('should delete a profile', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.profiles).toHaveLength(2);

        const createProfileButton = wrapper.find(
            '.sw-data-grid__row--0 .sw-import-export-view-profiles__listing-delete-action',
        );
        await createProfileButton.trigger('click');
        await flushPromises();

        document.body.querySelector('.mt-button--critical').click();
        await flushPromises();

        expect(wrapper.vm.profiles).toHaveLength(1);
    });
});
