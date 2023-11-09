/**
 * @package services-settings
 */
import { shallowMount } from '@vue/test-utils';
import swImportExportViewProfiles from 'src/module/sw-import-export/view/sw-import-export-view-profiles';
import ImportExportService from 'src/module/sw-import-export/service/importExport.service';

Shopware.Component.register('sw-import-export-view-profiles', swImportExportViewProfiles);

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-import-export-view-profiles'), {
        stubs: {
            'sw-card': true,
            'sw-simple-search-field': true,
            'sw-button': true,
            'sw-entity-listing': true,
            'sw-import-export-edit-profile-modal': true,
        },
        provide: {
            importExport: new ImportExportService(),
        },
    });
}

describe('src/module/sw-extension/component/sw-extension-card-base', () => {
    /** @type Wrapper */
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

    afterEach(async () => {
        if (wrapper) await wrapper.destroy();
    });

    it('should be a Vue.JS component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });
});
