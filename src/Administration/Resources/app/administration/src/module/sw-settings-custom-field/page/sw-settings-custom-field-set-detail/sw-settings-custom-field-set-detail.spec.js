/**
 * @package system-settings
 */
import { createLocalVue, shallowMount } from '@vue/test-utils';
import swSettingsCustomFieldSetDetail from 'src/module/sw-settings-custom-field/page/sw-settings-custom-field-set-detail';
import swCustomFieldList from 'src/module/sw-settings-custom-field/component/sw-custom-field-list';
import 'src/app/component/grid/sw-grid';
import 'src/app/component/grid/sw-pagination';

Shopware.Component.register('sw-settings-custom-field-set-detail', swSettingsCustomFieldSetDetail);
Shopware.Component.register('sw-custom-field-list', swCustomFieldList);

const set = {
    id: '9f359a2ab0824784a608fc2a443c5904',
};

const localVue = createLocalVue();
localVue.directive('tooltip', {});

async function createWrapper(privileges = []) {
    return shallowMount(await Shopware.Component.build('sw-settings-custom-field-set-detail'), {
        localVue,
        mocks: {
            $route: {
                params: {
                    id: '1234',
                },
            },
        },
        provide: {
            repositoryFactory: {
                create(repositoryName) {
                    if (repositoryName === 'custom_field') {
                        return {};
                    }

                    return {
                        get() {
                            return Promise.resolve(set);
                        },
                    };
                },
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                },
            },
        },
        stubs: {
            'sw-page': true,
            'sw-custom-field-set-detail-base': true,
            'sw-button': true,
            'sw-button-process': true,
            'sw-custom-field-list': true,
            'sw-card-view': true,
            'sw-icon': true,
            'sw-loader': true,
            'sw-skeleton': true,
        },
    });
}

describe('src/module/sw-settings-custom-field/page/sw-settings-custom-field-set-detail', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });
});
