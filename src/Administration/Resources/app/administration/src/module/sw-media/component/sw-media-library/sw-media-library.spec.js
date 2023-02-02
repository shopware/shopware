/**
 * @package content
 */
import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-media/mixin/media-grid-listener.mixin';

import swMediaLibrary from 'src/module/sw-media/component/sw-media-library/index';

Shopware.Component.register('sw-media-library', swMediaLibrary);

class Repository {
    constructor(entityName, amounts) {
        this.#entityName = entityName;
        this.#amounts = amounts;
    }

    #entityName = '';

    #amounts = [];

    invocation = 0;

    lastUsedCriteria;

    search(criteria) {
        const desiredAmount = this.#amounts[this.invocation];

        this.invocation += 1;
        this.lastUsedCriteria = criteria;

        const data = [];

        if (desiredAmount === null) {
            return Promise.reject();
        }

        for (let i = 0; i < desiredAmount; i += 1) {
            data.push({
                id: `${this.#entityName}-${this.invocation}-${i}`,
                getEntityName: () => this.#entityName
            });
        }

        return Promise.resolve(data);
    }
}


async function createWrapper({ mediaAmount, folderAmount } = { mediaAmount: [5], folderAmount: [5] }) {
    return shallowMount(await Shopware.Component.build('sw-media-library'), {
        propsData: {
            selection: [],
            limit: 5
        },

        stubs: {
            'sw-media-display-options': true,
            'sw-media-entity-mapper': true,
            'sw-media-grid': true,
            'sw-empty-state': true,
            'sw-skeleton': true,
            'sw-button': true
        },

        provide: {
            repositoryFactory: {
                create: (repositoryName) => {
                    switch (repositoryName) {
                        case 'media':
                            return new Repository('media', mediaAmount);
                        case 'media_folder':
                            return new Repository('folder', folderAmount);
                        case 'media_folder_configuration':
                            return {};
                        default:
                            throw new Error(`No Repository found for ${repositoryName}`);
                    }
                }
            },
            mediaService: {},
            searchRankingService: {}
        }
    });
}


describe('src/module/sw-media/component/sw-media-library/index', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should allow loading of additional folders', async () => {
        const wrapper = await createWrapper({ folderAmount: [5, 5, 3], mediaAmount: [5, 3] });

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        // Check that it starts with the correct amounts
        expect(wrapper.vm.subFolders.length).toBe(5);
        expect(wrapper.vm.items.length).toBe(5);
        expect(wrapper.vm.selectableItems.length).toBe(10);

        // Check that additional media and folders can be loaded
        expect(wrapper.vm.itemLoaderDone).toBe(false);
        expect(wrapper.vm.folderLoaderDone).toBe(false);

        // Initiate another load
        let loadMoreButton = wrapper.get('.sw-media-library__load-more-button');
        expect(loadMoreButton.exists()).toBe(true);
        wrapper.vm.loadNextItems();

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        // Check that appropriate amounts were loaded
        expect(wrapper.vm.subFolders.length).toBe(10);
        expect(wrapper.vm.items.length).toBe(8);
        expect(wrapper.vm.selectableItems.length).toBe(18);

        // Check that additional folders can be loaded, but not media
        expect(wrapper.vm.itemLoaderDone).toBe(true);
        expect(wrapper.vm.folderLoaderDone).toBe(false);

        // Initiate another load
        loadMoreButton = wrapper.get('.sw-media-library__load-more-button');
        expect(loadMoreButton.exists()).toBe(true);
        wrapper.vm.loadNextItems();

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        // Check that appropriate amounts were loaded
        expect(wrapper.vm.subFolders.length).toBe(13);
        expect(wrapper.vm.items.length).toBe(8);
        expect(wrapper.vm.selectableItems.length).toBe(21);

        // Check that no further media and folders can be loaded
        expect(wrapper.vm.itemLoaderDone).toBe(true);
        expect(wrapper.vm.folderLoaderDone).toBe(true);

        // Check that the 'Load more' button disappeared
        loadMoreButton = wrapper.find('.sw-media-library__load-more-button');
        expect(loadMoreButton.exists()).toBe(false);
    });

    it('should allow loading of additional media', async () => {
        const wrapper = await createWrapper({ folderAmount: [5, 3], mediaAmount: [5, 5, 3] });

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        // Check that it starts with the correct amounts
        expect(wrapper.vm.subFolders.length).toBe(5);
        expect(wrapper.vm.items.length).toBe(5);
        expect(wrapper.vm.selectableItems.length).toBe(10);

        // Check that more media and folders can be loaded
        expect(wrapper.vm.itemLoaderDone).toBe(false);
        expect(wrapper.vm.folderLoaderDone).toBe(false);

        // Initiate another load
        let loadMoreButton = wrapper.get('.sw-media-library__load-more-button');
        expect(loadMoreButton.exists()).toBe(true);
        wrapper.vm.loadNextItems();

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        // Check that appropriate amounts were loaded
        expect(wrapper.vm.subFolders.length).toBe(8);
        expect(wrapper.vm.items.length).toBe(10);
        expect(wrapper.vm.selectableItems.length).toBe(18);

        // Check that more media can be loaded, but not folders
        expect(wrapper.vm.itemLoaderDone).toBe(false);
        expect(wrapper.vm.folderLoaderDone).toBe(true);

        // Initiate another load
        loadMoreButton = wrapper.get('.sw-media-library__load-more-button');
        expect(loadMoreButton.exists()).toBe(true);
        wrapper.vm.loadNextItems();

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        // Check that appropriate amounts were loaded
        expect(wrapper.vm.subFolders.length).toBe(8);
        expect(wrapper.vm.items.length).toBe(13);
        expect(wrapper.vm.selectableItems.length).toBe(21);

        // Check that no further media and folders can be loaded
        expect(wrapper.vm.itemLoaderDone).toBe(true);
        expect(wrapper.vm.folderLoaderDone).toBe(true);

        // Check that the 'Load more' button disappeared
        loadMoreButton = wrapper.find('.sw-media-library__load-more-button');
        expect(loadMoreButton.exists()).toBe(false);
    });
    it('should limit association loading to 25', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.nextMedia();

        const usedCriteria = wrapper.vm.mediaRepository.lastUsedCriteria;

        expect(wrapper.vm.mediaRepository.invocation).toBe(2);

        [
            'tags',
            'productMedia.product',
            'categories',
            'productManufacturers.products',
            'mailTemplateMedia.mailTemplate',
            'documentBaseConfigs',
            'avatarUsers',
            'paymentMethods',
            'shippingMethods',
            'cmsBlocks.section.page',
            'cmsSections.page',
            'cmsPages'
        ].forEach(association => {
            const associationParts = association.split('.');

            let path = null;
            associationParts.forEach(currentPart => {
                path = path ? `${path}.${currentPart}` : currentPart;

                expect(usedCriteria.getAssociation(path).getLimit()).toBe(25);
            });
        });
    });

    it('should show the load more button if the folder request fails', async () => {
        const wrapper = await createWrapper({ folderAmount: [null, 3], mediaAmount: [3, undefined] });

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        // Check that it starts with the correct amounts
        expect(wrapper.vm.subFolders.length).toBe(0);
        expect(wrapper.vm.items.length).toBe(3);
        expect(wrapper.vm.selectableItems.length).toBe(3);

        // Check that additional media and folders can be loaded
        expect(wrapper.vm.itemLoaderDone).toBe(true);
        expect(wrapper.vm.folderLoaderDone).toBe(false);

        // Initiate another load
        let loadMoreButton = wrapper.get('.sw-media-library__load-more-button');
        expect(loadMoreButton.exists()).toBe(true);
        wrapper.vm.loadNextItems();

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        // Check that appropriate amounts were loaded
        expect(wrapper.vm.subFolders.length).toBe(3);
        expect(wrapper.vm.items.length).toBe(3);
        expect(wrapper.vm.selectableItems.length).toBe(6);

        // Check that additional folders can be loaded, but not media
        expect(wrapper.vm.itemLoaderDone).toBe(true);
        expect(wrapper.vm.folderLoaderDone).toBe(true);

        loadMoreButton = wrapper.find('.sw-media-library__load-more-button');
        expect(loadMoreButton.exists()).toBe(false);
    });

    it('should show the load more button if the media request fails', async () => {
        const wrapper = await createWrapper({ folderAmount: [3, undefined], mediaAmount: [null, 3] });

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        // Check that it starts with the correct amounts
        expect(wrapper.vm.subFolders.length).toBe(3);
        expect(wrapper.vm.items.length).toBe(0);
        expect(wrapper.vm.selectableItems.length).toBe(3);

        // Check that additional media and folders can be loaded
        expect(wrapper.vm.itemLoaderDone).toBe(false);
        expect(wrapper.vm.folderLoaderDone).toBe(true);

        // Initiate another load
        let loadMoreButton = wrapper.get('.sw-media-library__load-more-button');
        expect(loadMoreButton.exists()).toBe(true);
        wrapper.vm.loadNextItems();

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        // Check that appropriate amounts were loaded
        expect(wrapper.vm.subFolders.length).toBe(3);
        expect(wrapper.vm.items.length).toBe(3);
        expect(wrapper.vm.selectableItems.length).toBe(6);

        // Check that additional folders can be loaded, but not media
        expect(wrapper.vm.itemLoaderDone).toBe(true);
        expect(wrapper.vm.folderLoaderDone).toBe(true);

        loadMoreButton = wrapper.find('.sw-media-library__load-more-button');
        expect(loadMoreButton.exists()).toBe(false);
    });
});
