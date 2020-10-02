import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-sales-channel/view/sw-sales-channel-detail-analytics';

function createWrapper(privileges = []) {
    return shallowMount(Shopware.Component.build('sw-sales-channel-detail-analytics'), {
        stubs: {
            'sw-card': true,
            'sw-field': true,
            'sw-container': true
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    create: () => ({})
                })
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            }
        },
        mocks: {
            $tc: v => v
        },
        propsData: {
            salesChannel: {}
        }
    });
}

describe('src/module/sw-sales-channel/view/sw-sales-channel-detail-analytics', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have fields disabled when the user has no privileges', async () => {
        const wrapper = createWrapper();

        const fields = wrapper.findAll('sw-field-stub');

        fields.wrappers.forEach(field => {
            expect(field.attributes().disabled).toBe('true');
        });
    });

    it('should have fields enabled when the user has privileges', async () => {
        const wrapper = createWrapper([
            'sales_channel.editor'
        ]);

        const fields = wrapper.findAll('sw-field-stub');

        fields.wrappers.forEach(field => {
            expect(field.attributes().disabled).toBeUndefined();
        });
    });
});
