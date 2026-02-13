/**
 * @sw-package after-sales
 */

import { mount } from '@vue/test-utils';

const createWrapper = async (routeParams = {}) => {
    return mount(await wrapTestComponent('sw-mail-header-footer-create', { sync: true }), {
        global: {
            provide: {
                entityMappingService: {},
            },
            mocks: {
                $route: { params: { ...routeParams } },
            },
            stubs: {
                'sw-page': true,
                'sw-card-view': true,
                'sw-code-editor': true,
                'sw-entity-multi-select': true,
                'sw-language-info': true,
                'sw-skeleton': true,
                'sw-language-switch': true,
                'sw-button-process': true,
            },
        },
    });
};

describe('modules/sw-mail-template/page/sw-mail-header-footer-create', () => {
    it('should set mailHeaderFooterId to route id param when set', async () => {
        const mailHeaderFooterId = 'foo';

        const wrapper = await createWrapper({ id: mailHeaderFooterId });

        expect(wrapper.vm.mailHeaderFooterId).toEqual(mailHeaderFooterId);
    });

    it('should set mailHeaderFooterId when route id param not set', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.mailHeaderFooterId).not.toBeNull();
    });
});
