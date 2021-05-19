import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-flow/page/sw-flow-detail';

function createWrapper(privileges = []) {
    return shallowMount(Shopware.Component.build('sw-flow-detail'), {
        provide: { repositoryFactory: {
            create: () => ({
                create: () => {
                    return Promise.resolve({});
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
        } },

        stubs: {
            'sw-page': {
                template: `
                    <div class="sw-page">
                        <slot name="search-bar"></slot>
                        <slot name="smart-bar-back"></slot>
                        <slot name="smart-bar-header"></slot>
                        <slot name="language-switch"></slot>
                        <slot name="smart-bar-actions"></slot>
                        <slot name="side-content"></slot>
                        <slot name="content"></slot>
                        <slot name="sidebar"></slot>
                        <slot></slot>
                    </div>
                `
            },
            'sw-button': true,
            'sw-card-view': true,
            'sw-tabs': true,
            'sw-tabs-item': true,
            'router-view': true,
            'sw-button-process': true
        }
    });
}

describe('module/sw-flow/page/sw-flow-detail', () => {
    it('should be not able to save a flow ', async () => {
        const wrapper = createWrapper([
            'flow.editor'
        ]);
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.sw-flow-detail__save');
        expect(createButton.attributes().disabled).toBeFalsy();
    });

    it('should be able to save a flow ', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.sw-flow-detail__save');
        expect(createButton.attributes().disabled).toBeTruthy();
    });
});
