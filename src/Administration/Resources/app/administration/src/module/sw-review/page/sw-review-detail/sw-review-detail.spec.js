/**
 * @sw-package after-sales
 */
import { mount } from '@vue/test-utils';
import 'src/app/mixin/placeholder.mixin';
import 'src/app/mixin/salutation.mixin';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-review-detail', { sync: true }), {
        global: {
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
                        save: () => Promise.resolve(),
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
                'sw-button-process': true,
                'sw-search-bar': true,
                'sw-description-list': true,
                'sw-card-view': await wrapTestComponent('sw-card-view'),
                'mt-card': {
                    template: '<div><slot></slot></div>',
                },
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-loader': true,
                'sw-card-section': true,
                'sw-entity-single-select': true,
                'mt-textarea': true,
                'sw-language-switch': true,
                'sw-skeleton': true,
                'sw-rating-stars': true,
                'sw-custom-field-set-renderer': true,
                'sw-error-summary': true,
                'sw-time-ago': true,
            },
        },
    });
}

describe('module/sw-review/page/sw-review-detail', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should not be able to save the review', async () => {
        const wrapper = await createWrapper();

        const saveButton = wrapper.find('.sw-review-detail__save-action');

        expect(saveButton.attributes().disabled).toBe('true');
    });

    it('should be able to save the review', async () => {
        global.activeAclRoles = ['review.editor'];

        const wrapper = await createWrapper();
        await wrapper.setData({ isLoading: false });
        await flushPromises();

        const saveButton = wrapper.find('.sw-review-detail__save-action');

        expect(saveButton.attributes().disabled).toBe('false');
    });

    it('should not be able to edit review fields', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({ isLoading: false });
        await flushPromises();

        const languageField = wrapper.find('.sw-review__language-select');
        const activeField = wrapper.findComponent('.status-switch');
        const commentField = wrapper.find('.sw-review__comment-field');

        expect(languageField.attributes().disabled).toBe('true');
        expect(activeField.props().disabled).toBe(true);
        expect(commentField.attributes().disabled).toBe('true');
    });

    it('should be able to edit review fields', async () => {
        global.activeAclRoles = ['review.editor'];

        const wrapper = await createWrapper();
        await wrapper.setData({ isLoading: false });
        await flushPromises();

        const languageField = wrapper.find('.sw-review__language-select');
        const activeField = wrapper.find('.status-switch input');
        const commentField = wrapper.find('.sw-review__comment-field');

        expect(languageField.attributes().disabled).toBe('false');
        expect(activeField.attributes()).not.toHaveProperty('disabled');
        expect(commentField.attributes().disabled).toBe('false');
    });

    it('should return filters from filter registry', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        if (!Shopware.Feature.isActive('V6_8_0_0')) {
            // eslint-disable-next-line jest/no-conditional-expect
            expect(wrapper.vm.dateFilter).toEqual(expect.any(Function));
        }
    });

    it('should render loading indicator on save', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const saveButton = wrapper.find('.sw-review-detail__save-action');
        expect(saveButton.attributes('is-loading')).toBe('false');

        await saveButton.trigger('click');
        expect(saveButton.attributes('is-loading')).toBe('true');
    });
});
