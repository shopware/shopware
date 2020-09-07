import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-review/page/sw-review-detail';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-review-detail'), {
        localVue,
        mocks: {
            $tc: () => {},
            $route: {
                query: {
                    page: 1,
                    limit: 25
                },
                params: {
                    id: '12312'
                }
            },
            $router: {
                replace: () => {
                }
            },
            date: () => {}
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
                                name: 'Customer Number 1'
                            },
                            product: {
                                name: 'Product Number 1',
                                translated: {
                                    name: 'Product Number 1'
                                }
                            },
                            salesChannel: {
                                name: 'Channel Number 1',
                                translated: {
                                    name: 'Channel Number 1'
                                }
                            }
                        });
                    }
                })
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) {
                        return true;
                    }

                    return privileges.includes(identifier);
                }
            }
        },
        stubs: {
            'sw-page': `
                <div class="sw-page">
                    <slot name="smart-bar-actions"></slot>
                    <slot name="content">CONTENT</slot>
                    <slot></slot>
                </div>`,
            'sw-button': true,
            'sw-icon': true,
            'sw-search-bar': true,
            'sw-description-list': true,
            'sw-card-view': true,
            'sw-card': '<div><slot></slot></div>',
            'sw-container': true,
            'sw-loader': true,
            'sw-card-section': true,
            'sw-entity-single-select': true,
            'sw-switch-field': true,
            'sw-textarea-field': true,
            'sw-language-switch': true
        }
    });
}

describe('module/sw-review/page/sw-review-detail', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.isVueInstance()).toBe(true);
    });

    it('should not be able to save the review', () => {
        const wrapper = createWrapper();

        const saveButton = wrapper.find('.sw-review-detail__save-action');

        expect(saveButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to save the review', async () => {
        const wrapper = createWrapper([
            'review.editor'
        ]);
        wrapper.setData({
            isLoading: false
        });
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.sw-review-detail__save-action');

        expect(saveButton.attributes().disabled).toBeFalsy();
    });

    it('should not be able to edit review fields', () => {
        const wrapper = createWrapper();

        const languageField = wrapper.find('.sw-review__language-select');
        const activeField = wrapper.find('.status-switch');
        const commentField = wrapper.find('.sw-review__comment-field');

        expect(languageField.attributes().disabled).toBeTruthy();
        expect(activeField.attributes().disabled).toBeTruthy();
        expect(commentField.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit review fields', () => {
        const wrapper = createWrapper([
            'review.editor'
        ]);

        const languageField = wrapper.find('.sw-review__language-select');
        const activeField = wrapper.find('.status-switch');
        const commentField = wrapper.find('.sw-review__comment-field');

        expect(languageField.attributes().disabled).toBeFalsy();
        expect(activeField.attributes().disabled).toBeFalsy();
        expect(commentField.attributes().disabled).toBeFalsy();
    });
});
