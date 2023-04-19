/**
 * @package content
 */
import { shallowMount } from '@vue/test-utils';
import SwMediaField from 'src/app/asyncComponent/media/sw-media-field';

Shopware.Component.register('sw-media-field', SwMediaField);

describe('src/app/component/media/sw-media-field', () => {
    async function createWrapper() {
        return shallowMount(await Shopware.Component.build('sw-media-field'), {
            stubs: {
                'sw-media-media-item': true,
                'sw-button': true,
                'sw-popover': true,
                'sw-upload-listener': true,
                'sw-media-upload-v2': true,
                'sw-simple-search-field': true,
                'sw-loader': true,
                'sw-icon': true,
            },
            mocks: {
                $route: {
                    query: '',
                },
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        create: () => {
                            return Promise.resolve();
                        },
                        get: () => {
                            return Promise.resolve();
                        },
                        search: () => {
                            return Promise.resolve();
                        },
                    }),
                },
            },
            propsData: {
                fileAccept: '*/*',
            },
        });
    }

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain the default folder in criteria', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            defaultFolder: 'product',
        });
        const criteria = wrapper.vm.suggestionCriteria;
        expect(criteria.filters).toContainEqual({
            type: 'equals',
            field: 'mediaFolder.defaultFolder.entity',
            value: 'product',
        });
    });

    it('should contain a property props fileAccept', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.$props.fileAccept).toBe('*/*');
    });
});
