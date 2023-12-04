/**
 * @package inventory
 */
import { createLocalVue, shallowMount } from '@vue/test-utils_v2';
import swReviewDetail from 'src/module/sw-review/page/sw-review-detail';
import 'src/app/mixin/placeholder.mixin';
import 'src/app/mixin/salutation.mixin';

Shopware.Component.register('sw-review-detail', swReviewDetail);

async function createWrapper() {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(await Shopware.Component.build('sw-review-detail'), {
        localVue,
        mocks: {
            $route: {
                query: {
                    page: 1,
                    limit: 25,
                },
                params: {
                    id: '12312',
                },
            },
            date: () => {},
            placeholder: () => {},
            salutation: () => {},
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    get: () => {
                        return Promise.resolve({
                            id: '1a2b3c',
                            entity: 'review',
                            customerId: 'd4c3b2a1',
                            productId: 'd4c3b2a1',
                            salesChannelId: 'd4c3b2a1',
                            customer: {
                                name: 'Customer Number 1',
                            },
                            product: {
                                name: 'Product Number 1',
                                translated: {
                                    name: 'Product Number 1',
                                },
                            },
                            salesChannel: {
                                name: 'Channel Number 1',
                                translated: {
                                    name: 'Channel Number 1',
                                },
                            },
                        });
                    },
                }),
            },
            customFieldDataProviderService: {
                getCustomFieldSets: () => Promise.resolve([]),
            },
        },
        stubs: {
            'sw-page': {
                template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content">CONTENT</slot>
                        <slot></slot>
                    </div>`,
            },
            'sw-button': true,
            'sw-button-process': true,
            'sw-icon': true,
            'sw-search-bar': true,
            'sw-description-list': true,
            'sw-card-view': true,
            'sw-card': {
                template: '<div><slot></slot></div>',
            },
            'sw-container': true,
            'sw-loader': true,
            'sw-card-section': true,
            'sw-entity-single-select': true,
            'sw-switch-field': true,
            'sw-textarea-field': true,
            'sw-language-switch': true,
            'sw-skeleton': true,
        },
    });
}

describe('module/sw-review/page/sw-review-detail', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not be able to save the review', async () => {
        const wrapper = await createWrapper();

        const saveButton = wrapper.find('.sw-review-detail__save-action');

        expect(saveButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to save the review', async () => {
        global.activeAclRoles = ['review.editor'];

        const wrapper = await createWrapper();
        await wrapper.setData({
            isLoading: false,
        });
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.sw-review-detail__save-action');

        expect(saveButton.attributes().disabled).toBeFalsy();
    });

    it('should not be able to edit review fields', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({ isLoading: false });

        const languageField = wrapper.find('.sw-review__language-select');
        const activeField = wrapper.find('.status-switch');
        const commentField = wrapper.find('.sw-review__comment-field');

        expect(languageField.attributes().disabled).toBeTruthy();
        expect(activeField.attributes().disabled).toBeTruthy();
        expect(commentField.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit review fields', async () => {
        global.activeAclRoles = ['review.editor'];

        const wrapper = await createWrapper();

        await wrapper.setData({ isLoading: false });

        const languageField = wrapper.find('.sw-review__language-select');
        const activeField = wrapper.find('.status-switch');
        const commentField = wrapper.find('.sw-review__comment-field');

        expect(languageField.attributes().disabled).toBeFalsy();
        expect(activeField.attributes().disabled).toBeFalsy();
        expect(commentField.attributes().disabled).toBeFalsy();
    });

    it('should return filters from filter registry', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.dateFilter).toEqual(expect.any(Function));
    });
});
