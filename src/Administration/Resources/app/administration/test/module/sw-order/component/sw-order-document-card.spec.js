import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-order/component/sw-order-document-card';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-order-document-card'), {
        localVue,
        stubs: {
            'sw-card': {
                template: '<div><slot name="grid"></slot></div>'
            },
            'sw-empty-state': true,
            'sw-card-section': true,
            'sw-card-filter': {
                template: '<div><slot name="filter"></slot></div>'
            },
            'sw-context-button': true,
            'sw-button': true
        },
        provide: {
            acl: {
                can: (key) => {
                    if (!key) { return true; }

                    return privileges.includes(key);
                }
            },
            documentService: {},
            numberRangeService: {},
            repositoryFactory: {
                create: () => ({
                    search: () => Promise.resolve([])
                })
            }
        },
        mocks: {
            $router: {
                replace: () => {}
            },
            $route: {
                query: ''
            },
            $tc: v => v
        },
        propsData: {
            order: {},
            isLoading: false
        }
    });
}

describe('src/module/sw-order/component/sw-order-document-card', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have an disabled create new button', async () => {
        const createNewButton = wrapper.find('.sw-order-document-grid-button');

        expect(createNewButton.attributes().disabled).toBe('true');
    });

    it('should not have an disabled create new button', async () => {
        wrapper = createWrapper([
            'order.editor'
        ]);
        const createNewButton = wrapper.find('.sw-order-document-grid-button');

        expect(createNewButton.attributes().disabled).toBeUndefined();
    });
});
