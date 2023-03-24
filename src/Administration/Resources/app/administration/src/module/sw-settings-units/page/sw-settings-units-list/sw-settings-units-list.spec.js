import { createLocalVue, shallowMount } from '@vue/test-utils';
import swSettingsUnitsList from 'src/module/sw-settings-units/page/sw-settings-units-list';

Shopware.Component.register('sw-settings-units-list', swSettingsUnitsList);

async function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(await Shopware.Component.build('sw-settings-units-list'), {
        localVue,
        mocks: {
            $route: {
                query: {
                    page: 1,
                    limit: 25
                }
            },
            $tc() {
                return 'trans';
            },
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    search() {
                        return Promise.resolve([
                            {
                                id: '1a2b3c',
                                name: 'Gramm',
                                shortCode: 'g'
                            }
                        ]);
                    },
                    save(unit) {
                        if (unit.id !== 'success') {
                            return Promise.reject();
                        }

                        return Promise.resolve();
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
            }
        },
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
            'sw-data-grid': {
                props: ['dataSource'],
                template: `
                    <div>
                        <template v-for="item in dataSource">
                            <slot name="actions" v-bind="{ item }"></slot>
                        </template>
                    </div>`
            },
            'sw-search-bar': true,
            'sw-icon': true,
            'sw-language-switch': true,
            'sw-button': true,
            'sw-card': {
                template: '<div><slot></slot><slot name="grid"></slot></div>'
            },
            'sw-card-view': {
                template: `
                        <div class="sw-card-view">
                            <slot></slot>
                        </div>
                    `
            },
            'sw-empty-state': true,
            'sw-context-menu-item': true,
            'sw-context-menu-divider': true,
        }
    });
}

describe('module/sw-settings-units/page/sw-settings-units', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should create meta info', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.$options.$createTitle = () => 'meta';
        const metaInfo = wrapper.vm.$options.metaInfo();

        expect(typeof metaInfo).toBe('object');
        expect(metaInfo.hasOwnProperty('title')).toBeTruthy();
        expect(metaInfo.title).toBe('meta');
    });

    it('should push to new route on unit creation', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.$router.push = jest.fn();

        wrapper.vm.createNewUnit();

        expect(wrapper.vm.$router.push).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({
            name: 'sw.settings.units.create',
        });
    });

    it('should be able to create a new units', async () => {
        const wrapper = await createWrapper([
            'scale_unit.creator'
        ]);
        await wrapper.vm.$nextTick();

        const addButton = wrapper.find('.sw-settings-units__create-action');

        expect(addButton.attributes().disabled).toBeFalsy();
    });

    it('should not be able to create a new units', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const addButton = wrapper.find('.sw-settings-units__create-action');

        expect(addButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit a unit', async () => {
        const wrapper = await createWrapper([
            'scale_unit.editor'
        ]);
        await wrapper.vm.$nextTick();

        const dataGrid = wrapper.find('.sw-settings-units-grid');

        expect(dataGrid.exists()).toBeTruthy();
        expect(dataGrid.attributes()['allow-inline-edit']).toBeTruthy();
    });

    it('should not be able to edit a unit', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const dataGrid = wrapper.find('.sw-settings-units-grid');

        expect(dataGrid.exists()).toBeTruthy();
        expect(dataGrid.attributes()['allow-inline-edit']).toBeFalsy();
    });

    it('should be able to delete a units', async () => {
        const wrapper = await createWrapper([
            'scale_unit.deleter'
        ]);
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-settings-units__delete-action');

        expect(deleteMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to delete a units', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-settings-units__delete-action');

        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should save unit', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.createNotificationSuccess = jest.fn();

        wrapper.vm.saveUnit({
            id: 'success',
        });
        await flushPromises();

        expect(wrapper.vm.newUnit).toBe(null);
        expect(wrapper.vm.createNotificationSuccess).toHaveBeenCalledTimes(1);
    });

    it('should display error on save unit fail', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.createNotificationError = jest.fn();

        wrapper.vm.saveUnit({
            id: 'fail',
        });
        await flushPromises();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledTimes(1);
    });

    it('should delete unit', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.unitRepository.delete = jest.fn(() => {
            return Promise.resolve();
        });

        wrapper.vm.deleteUnit({
            id: '12345',
        });
        await flushPromises();

        expect(wrapper.vm.unitRepository.delete).toHaveBeenCalledTimes(1);
    });

    it('should return unit columns', async () => {
        const wrapper = await createWrapper();

        const columns = wrapper.vm.unitColumns();

        expect(columns).toStrictEqual(
            [
                {
                    property: 'name',
                    label: 'sw-settings-units.grid.columnName',
                    routerLink: 'sw.settings.units.detail',
                },
                {
                    property: 'shortCode',
                    label: 'sw-settings-units.grid.columnShortCode',
                }
            ]
        );
    });
});
