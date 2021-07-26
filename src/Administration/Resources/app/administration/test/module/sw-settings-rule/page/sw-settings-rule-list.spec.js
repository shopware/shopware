import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-rule/page/sw-settings-rule-list';
import 'src/app/component/context-menu/sw-context-menu';
import 'src/app/component/context-menu/sw-context-menu-item';

function createWrapper(privileges = []) {
    return shallowMount(Shopware.Component.build('sw-settings-rule-list'), {
        stubs: {
            'sw-page': {
                template: `
    <div>
        <slot name="smart-bar-actions"></slot>
        <slot name="content"></slot>
    </div>`
            },
            'sw-button': true,
            'sw-empty-state': true,
            'sw-loader': true,
            'sw-entity-listing': {
                template: `
    <div class="sw-entity-listing">
        <slot name="more-actions"></slot>
    </div>
    `
            },
            'sw-context-menu-item': Shopware.Component.build('sw-context-menu-item')
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => Promise.resolve([

                    ])
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
            $route: {
                query: 'foo'
            }
        }
    });
}

describe('src/module/sw-settings-rule/page/sw-settings-rule-list', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have disabled fields', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const buttonAddRule = wrapper.find('sw-button-stub');
        const entityListing = wrapper.find('.sw-entity-listing');
        const contextMenuItemDuplicate = wrapper.find('.sw-context-menu-item');

        expect(buttonAddRule.attributes().disabled).toBe('true');
        expect(entityListing.attributes()['show-selection']).toBeUndefined();
        expect(entityListing.attributes()['allow-edit']).toBeUndefined();
        expect(entityListing.attributes()['allow-delete']).toBeUndefined();
        expect(contextMenuItemDuplicate.attributes().class).toContain('is--disabled');
    });

    it('should have enabled fields for creator', async () => {
        const wrapper = createWrapper([
            'rule.creator'
        ]);
        await wrapper.vm.$nextTick();

        const buttonAddRule = wrapper.find('sw-button-stub');
        const entityListing = wrapper.find('.sw-entity-listing');
        const contextMenuItemDuplicate = wrapper.find('.sw-context-menu-item');

        expect(buttonAddRule.attributes().disabled).toBeUndefined();
        expect(entityListing.attributes()['show-selection']).toBeUndefined();
        expect(entityListing.attributes()['allow-edit']).toBeUndefined();
        expect(entityListing.attributes()['allow-delete']).toBeUndefined();
        expect(contextMenuItemDuplicate.attributes().class).not.toContain('is--disabled');
    });

    it('only should have enabled fields for editor', async () => {
        const wrapper = createWrapper([
            'rule.editor'
        ]);
        await wrapper.vm.$nextTick();

        const buttonAddRule = wrapper.find('sw-button-stub');
        const entityListing = wrapper.find('.sw-entity-listing');
        const contextMenuItemDuplicate = wrapper.find('.sw-context-menu-item');

        expect(buttonAddRule.attributes().disabled).toBe('true');
        expect(entityListing.attributes()['show-selection']).toBeUndefined();
        expect(entityListing.attributes()['allow-edit']).toBe('true');
        expect(entityListing.attributes()['allow-delete']).toBeUndefined();
        expect(contextMenuItemDuplicate.attributes().class).toContain('is--disabled');
    });

    it('should have enabled fields for deleter', async () => {
        const wrapper = createWrapper([
            'rule.deleter'
        ]);
        await wrapper.vm.$nextTick();

        const buttonAddRule = wrapper.find('sw-button-stub');
        const entityListing = wrapper.find('.sw-entity-listing');
        const contextMenuItemDuplicate = wrapper.find('.sw-context-menu-item');

        expect(buttonAddRule.attributes().disabled).toBe('true');
        expect(entityListing.attributes()['show-selection']).toBe('true');
        expect(entityListing.attributes()['allow-edit']).toBeUndefined();
        expect(entityListing.attributes()['allow-delete']).toBe('true');
        expect(contextMenuItemDuplicate.attributes().class).toContain('is--disabled');
    });

    it('should duplicate a rule and should overwrite name and createdAt values', async () => {
        const wrapper = await createWrapper(['rule.creator']);
        wrapper.vm.onDuplicate = jest.fn();
        wrapper.vm.onDuplicate.mockReturnValueOnce('hi');

        await wrapper.vm.$nextTick();

        const contextMenuItemDuplicate = wrapper.find('.sw-context-menu-item');
        await contextMenuItemDuplicate.trigger('click');

        expect(wrapper.vm.onDuplicate)
            .toHaveBeenCalledTimes(1);
    });
});
