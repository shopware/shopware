/**
 * @sw-package framework
 */
import 'src/module/sw-settings/mixin/sw-settings-list.mixin';
import { shallowMount } from '@vue/test-utils';

async function createWrapper(existingSnippetKeys = []) {
    return shallowMount(
        {
            template: '<div></div>',
            mixins: [Shopware.Mixin.getByName('sw-settings-list')],
            data() {
                return {
                    entityName: 'number_range',
                    deleteEntity: { name: 'Invoices' },
                };
            },
        },
        {
            global: {
                mocks: {
                    $te: (key) => existingSnippetKeys.includes(key),
                    $t: (key, named) => `${key} ${JSON.stringify(named)}`,
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({ search: () => Promise.resolve([]) }),
                    },
                    searchRankingService: {},
                },
            },
        },
    );
}

describe('src/module/sw-settings/mixin/sw-settings-list.mixin.js', () => {
    it('uses the module specific delete success message when it exists', async () => {
        const wrapper = await createWrapper(['sw-settings-number-range.list.messageDeleteSuccess']);

        expect(wrapper.vm.messageSaveSuccess).toBe('sw-settings-number-range.list.messageDeleteSuccess {"name":"Invoices"}');
    });

    it('falls back to the global delete success message', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.messageSaveSuccess).toBe('global.notification.messageDeleteSuccess {"name":"Invoices"}');
    });
});
