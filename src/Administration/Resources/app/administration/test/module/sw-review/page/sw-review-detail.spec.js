import {createLocalVue, shallowMount} from '@vue/test-utils';
import 'src/module/sw-review/page/sw-review-detail';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-review-detail'), {
        localVue,
        mocks: {
            $tc: () => {
            },
            $route: {
                query: {
                    page: 1,
                    limit: 25
                }
            },
            $router: {
                replace: () => {
                }
            }
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
                                name: 'Product Number 1'
                            },
                            salesChannel: {
                                name: 'Channel Number 1'
                            }
                        });
                    },
                    search: () => Promise.resolve({})
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
});
