/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

const extensions = [
    {
        name: 'CompatibleExtension',
        statusMessage: 'compatible',
        statusVariant: 'success',
        statusColor: '',
    },
    {
        name: 'IncompatibleExtension',
        statusMessage: 'notCompatible',
        statusVariant: 'danger',
        statusColor: '',
    },
    {
        name: 'UnknownExtension',
        statusMessage: '',
        statusVariant: '',
        statusColor: 'grey',
    },
];

async function createWrapper(props: Record<string, unknown> = {}, routerPush: jest.Mock = jest.fn()) {
    return mount(await wrapTestComponent('sw-settings-shopware-updates-extensions', { sync: true }), {
        props: {
            isLoading: false,
            extensions,
            ...props,
        },
        global: {
            provide: {
                feature: {},
            },
            mocks: {
                $router: {
                    push: routerPush,
                },
            },
            stubs: {
                'mt-card': {
                    props: [
                        'title',
                        'isLoading',
                    ],
                    template: `
                        <div class="mt-card" :data-is-loading="String(isLoading)" :data-title="title">
                            <slot name="grid"></slot>
                        </div>
                    `,
                },
                'sw-data-grid': {
                    props: [
                        'dataSource',
                        'columns',
                    ],
                    template: `
                        <div class="sw-data-grid">
                            <div
                                v-for="item in dataSource"
                                :key="item.name"
                                class="sw-data-grid__row"
                            >
                                <slot name="column-icon" :item="item"></slot>
                                <slot name="actions" :item="item"></slot>
                            </div>
                        </div>
                    `,
                },
                'sw-color-badge': true,
                'sw-context-menu-item': {
                    template: '<div class="sw-context-menu-item" @click="$emit(\'click\')"><slot></slot></div>',
                },
            },
        },
    });
}

describe('src/module/sw-settings-shopware-updates/view/sw-settings-shopware-updates-extensions', () => {
    it('renders a row per extension', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.findAll('.sw-data-grid__row')).toHaveLength(3);
    });

    it('passes the loading state and title to the card', async () => {
        const wrapper = await createWrapper({ isLoading: true });

        const card = wrapper.get('.mt-card');
        expect(card.attributes('data-is-loading')).toBe('true');
        expect(card.attributes('data-title')).toBe('sw-settings-shopware-updates.cards.extensions');
    });

    it('defines name and availability columns', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.columns).toEqual([
            {
                property: 'name',
                label: 'sw-settings-shopware-updates.extensions.columns.name',
                rawData: true,
            },
            {
                property: 'icon',
                label: 'sw-settings-shopware-updates.extensions.columns.available',
                rawData: true,
            },
        ]);
    });

    it('shows the deactivation hint for incompatible extensions', async () => {
        const wrapper = await createWrapper();

        const rows = wrapper.findAll('.sw-data-grid__row');
        expect(rows[1].text()).toContain('notCompatible');
        expect(rows[1].text()).toContain('sw-settings-shopware-updates.extensions.extensionWillBeDeactivatedHint');
    });

    it('shows the plain status message when it is not "notCompatible"', async () => {
        const wrapper = await createWrapper();

        const rows = wrapper.findAll('.sw-data-grid__row');
        expect(rows[0].text()).toContain('compatible');
        expect(rows[0].text()).not.toContain('sw-settings-shopware-updates.extensions.extensionWillBeDeactivatedHint');
    });

    it('shows the not-in-store hint when there is no status message', async () => {
        const wrapper = await createWrapper();

        const rows = wrapper.findAll('.sw-data-grid__row');
        expect(rows[2].text()).toContain('sw-settings-shopware-updates.extensions.extensionNotInStore');
    });

    it('navigates to my extensions from the row action', async () => {
        const routerPush = jest.fn();
        const wrapper = await createWrapper({}, routerPush);

        await wrapper.find('.sw-context-menu-item').trigger('click');

        expect(routerPush).toHaveBeenCalledWith({
            name: 'sw.extension.my-extensions.listing.app',
        });
    });
});
