import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-mail-template/page/sw-mail-template-index';

// Turn off known errors
import { missingGetListMethod } from 'src/../test/_helper_/allowedErrors';

global.allowedErrors = [missingGetListMethod];

const createWrapper = () => {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-mail-template-index'), {
        localVue,
        mocks: {
            $route: {
                query: {
                    page: 1,
                    limit: 25
                }
            }
        },
        stubs: {
            'sw-page': {
                template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot></slot>
                    </div>`
            },
            'sw-card-view': {
                template: '<div><slot></slot></div>'
            },
            'sw-container': {
                template: '<div><slot></slot></div>'
            },
            'sw-button': true,
            'sw-context-button': {
                template: `
                    <div class="sw-context-button">
                        <slot name="button"></slot>
                        <slot></slot>
                     </div>`
            },
            'sw-context-menu-item': true,
            'sw-icon': true
        }
    });
};

describe('modules/sw-mail-template/page/sw-mail-template-index', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });
    it('should not allow to create', () => {
        const wrapper = createWrapper();

        const createButton = wrapper.find('.sw-context-button');
        const innerButton = createButton.find('sw-button-stub');

        expect(createButton.attributes().disabled).toBeTruthy();
        expect(innerButton.attributes().disabled).toBeTruthy();
    });

    it('should allow to create', () => {
        global.activeAclRoles = ['mail_templates.creator'];

        const wrapper = createWrapper();

        const createButton = wrapper.find('.sw-context-button');
        const innerButton = createButton.find('sw-button-stub');

        expect(createButton.attributes().disabled).toBeFalsy();
        expect(innerButton.attributes().disabled).toBeFalsy();
    });
});
