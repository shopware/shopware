import { shallowMount, createLocalVue } from '@vue/test-utils';
import swMailTemplateIndex from 'src/module/sw-mail-template/page/sw-mail-template-index';

Shopware.Component.register('sw-mail-template-index', swMailTemplateIndex);

const createWrapper = async () => {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(await Shopware.Component.build('sw-mail-template-index'), {
        localVue,
        provide: {
            searchRankingService: {},
        },
        mocks: {
            $route: {
                query: {
                    page: 1,
                    limit: 25,
                },
            },
        },
        stubs: {
            'sw-page': {
                template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot></slot>
                    </div>`,
            },
            'sw-card-view': {
                template: '<div><slot></slot></div>',
            },
            'sw-container': {
                template: '<div><slot></slot></div>',
            },
            'sw-button': true,
            'sw-context-button': {
                template: `
                    <div class="sw-context-button">
                        <slot name="button"></slot>
                        <slot></slot>
                     </div>`,
            },
            'sw-context-menu-item': true,
            'sw-icon': true,
        },
    });
};

describe('modules/sw-mail-template/page/sw-mail-template-index', () => {
    beforeEach(async () => {
        global.activeAclRoles = [];

        // TODO: Remove this when the test is fixed
        global.allowedErrors = [
            {
                method: 'warn',
                msg: '[Listing Mixin]',
            },
        ];
    });

    it('should not allow to create', async () => {
        const wrapper = await createWrapper();

        const createButton = wrapper.find('.sw-context-button');
        const innerButton = createButton.find('sw-button-stub');

        expect(createButton.attributes().disabled).toBeTruthy();
        expect(innerButton.attributes().disabled).toBeTruthy();
    });

    it('should allow to create', async () => {
        global.activeAclRoles = ['mail_templates.creator'];

        const wrapper = await createWrapper();

        const createButton = wrapper.find('.sw-context-button');
        const innerButton = createButton.find('sw-button-stub');

        expect(createButton.attributes().disabled).toBeFalsy();
        expect(innerButton.attributes().disabled).toBeFalsy();
    });
});
