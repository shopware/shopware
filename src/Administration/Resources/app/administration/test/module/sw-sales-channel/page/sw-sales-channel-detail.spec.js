import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-sales-channel/page/sw-sales-channel-detail';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-sales-channel-detail'), {
        localVue,
        stubs: {
            'sw-page': {
                template: `
<div class="sw-page">
    <slot name="smart-bar-actions"></slot>
</div>
                `
            },
            'sw-button-process': true,
            'sw-language-switch': true,
            'sw-card-view': true,
            'sw-language-info': true,
            'sw-tabs': true,
            'sw-tabs-item': true,
            'router-view': true
        },
        provide: {
            acl: {
                can: (privilegeKey) => {
                    if (!privilegeKey) { return true; }

                    return privileges.includes(privilegeKey);
                }
            },
            repositoryFactory: {
                create: () => ({
                    create: () => ({}),
                    get: () => Promise.resolve({
                        productExports: {
                            first: () => ({})
                        }
                    }),
                    search: () => Promise.resolve([])
                })
            },
            exportTemplateService: {
                getProductExportTemplateRegistry: () => ({})
            }
        },
        mocks: {
            $route: {
                params: {
                    id: '1a2b3c4d'
                },
                name: ''
            }
        }
    });
}

describe('src/module/sw-sales-channel/page/sw-sales-channel-detail', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
        wrapper.destroy();
    });

    it('should disable the save button when privilege does not exists', async () => {
        const wrapper = createWrapper();
        const saveButton = wrapper.find('.sw-sales-channel-detail__save-action');

        await wrapper.setData({
            isLoading: false
        });

        expect(saveButton.attributes().disabled).toBeTruthy();
        wrapper.destroy();
    });

    it('should enable the save button when privilege does exists', async () => {
        const wrapper = createWrapper([
            'sales_channel.editor'
        ]);

        await wrapper.setData({
            isLoading: false
        });

        const saveButton = wrapper.find('.sw-sales-channel-detail__save-action');

        expect(saveButton.attributes().disabled).toBeFalsy();
        wrapper.destroy();
    });
});
