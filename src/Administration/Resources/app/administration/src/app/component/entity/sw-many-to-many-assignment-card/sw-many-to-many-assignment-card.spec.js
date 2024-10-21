/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

async function createWrapper(customPropsData = {}) {
    const entityCollection = [];
    entityCollection.context = {
        languageId: '1a2b3c',
    };

    return mount(
        await wrapTestComponent('sw-many-to-many-assignment-card', {
            sync: true,
        }),
        {
            props: {
                columns: [],
                entityCollection: entityCollection,
                localMode: true,
                ...customPropsData,
            },
            global: {
                stubs: {
                    'sw-card': {
                        template: '<div><slot></slot><slot name="grid"></slot></div>',
                    },
                    'sw-select-base': {
                        template: '<div class="sw-select-base"></div>',
                    },
                    'sw-data-grid': {
                        template: '<div><slot name="actions"></slot></div>',
                    },
                    'sw-context-menu': true,
                    'sw-context-menu-item': true,
                    'sw-icon': true,
                    'sw-highlight-text': true,
                    'sw-select-result': true,
                    'sw-select-result-list': true,
                    'sw-pagination': true,
                },
                provide: {
                    repositoryFactory: {},
                },
            },
        },
    );
}

describe('src/app/component/entity/sw-many-to-many-assignment-card', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have an enabled sw-select-base', async () => {
        const wrapper = await createWrapper();

        const selectBase = wrapper.find('.sw-select-base');

        expect(selectBase.attributes().disabled).toBeUndefined();
    });

    it('should have an disabled sw-select-base', async () => {
        const wrapper = await createWrapper({ disabled: true });

        const selectBase = wrapper.find('.sw-select-base');

        expect(selectBase.attributes().disabled).toBeDefined();
    });

    it('should have an enabled context menu item', async () => {
        const wrapper = await createWrapper();

        const selectBase = wrapper.find('sw-context-menu-item-stub');

        expect(selectBase.attributes().disabled).toBeUndefined();
    });

    it('should have an disabled context menu item', async () => {
        const wrapper = await createWrapper({ disabled: true });

        const selectBase = wrapper.find('sw-context-menu-item-stub');

        expect(selectBase.attributes().disabled).toBeDefined();
    });
});
