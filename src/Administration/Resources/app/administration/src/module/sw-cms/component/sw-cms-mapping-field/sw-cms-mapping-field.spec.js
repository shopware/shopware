/**
 * @sw-package discovery
 */
import { flushPromises, mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

const mediaEntity = {
    id: 'media-id',
    url: 'http://shopware.com/custom-field-image.jpg',
};

async function createWrapper(config = { source: 'mapped', value: 'category.customFields.heroImage' }) {
    return mount(
        await wrapTestComponent('sw-cms-mapping-field', {
            sync: true,
        }),
        {
            props: {
                config,
                valueTypes: 'entity',
                entity: 'media',
                label: 'Media',
            },
            global: {
                provide: {
                    cmsService: Shopware.Service('cmsService'),
                    repositoryFactory: {
                        create: () => ({
                            get: jest.fn().mockResolvedValue(mediaEntity),
                        }),
                    },
                },
                stubs: {
                    'sw-context-button': true,
                    'sw-context-menu-item': true,
                    'mt-icon': true,
                    'mt-banner': true,
                },
            },
        },
    );
}

describe('src/module/sw-cms/component/sw-cms-mapping-field', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
    });

    beforeEach(() => {
        const cmsPageStore = Shopware.Store.get('cmsPage');
        cmsPageStore.setCurrentMappingEntity('category');
        cmsPageStore.setCurrentMappingTypes({
            entity: {
                media: ['category.customFields.heroImage'],
            },
        });
        cmsPageStore.setCurrentDemoEntity({
            customFields: {
                heroImage: 'media-id',
            },
        });
    });

    it('resolves mapped media ids to media entities for entity previews', async () => {
        const wrapper = await createWrapper();

        await flushPromises();

        expect(wrapper.vm.demoValue).toEqual(mediaEntity);
    });

    it('keeps object demo values unchanged', async () => {
        Shopware.Store.get('cmsPage').setCurrentDemoEntity({
            media: mediaEntity,
        });

        const wrapper = await createWrapper({
            source: 'mapped',
            value: 'category.media',
        });

        await flushPromises();

        expect(wrapper.vm.demoValue).toEqual(mediaEntity);
    });
});
