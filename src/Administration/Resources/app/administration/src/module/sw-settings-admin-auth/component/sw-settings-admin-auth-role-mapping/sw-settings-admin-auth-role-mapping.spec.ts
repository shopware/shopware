/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

interface MappingRow {
    id: string;
    group: string;
    roles: string[];
}

interface RoleMappingVm {
    rows: MappingRow[];
    buildMapping(): Record<string, string[]>;
    onGroupChange(row: MappingRow, group: string): void;
    onRolesChange(row: MappingRow, roles: string[]): void;
}

function getVm(wrapper: { vm: unknown }): RoleMappingVm {
    return wrapper.vm as RoleMappingVm;
}

async function createWrapper(props = {}) {
    const wrapper = mount(await wrapTestComponent('sw-settings-admin-auth-role-mapping', { sync: true }), {
        props: {
            mapping: { 'idp-admins': ['admin'] },
            roleOptions: [
                { value: 'admin', label: 'Administrator' },
                { value: 'catalog-editor', label: 'catalog-editor' },
            ],
            ...props,
        },
        global: {
            stubs: {
                'sw-multi-select': true,
            },
        },
    });

    await flushPromises();

    return wrapper;
}

describe('module/sw-settings-admin-auth/component/sw-settings-admin-auth-role-mapping', () => {
    it('should build one editor row per mapped group', async () => {
        const wrapper = await createWrapper({
            mapping: {
                'idp-admins': ['admin'],
                'idp-catalog': ['catalog-editor'],
            },
        });

        const rows = wrapper.findAll('.sw-settings-admin-auth-role-mapping__row');
        expect(rows).toHaveLength(2);
        expect(getVm(wrapper).rows.map((row) => row.group)).toEqual([
            'idp-admins',
            'idp-catalog',
        ]);
    });

    it('should add an empty row without emitting it until a group name is set', async () => {
        const wrapper = await createWrapper();

        await wrapper.get('.sw-settings-admin-auth-role-mapping__add-action').trigger('click');

        expect(wrapper.findAll('.sw-settings-admin-auth-role-mapping__row')).toHaveLength(2);
        // an incomplete row must not appear in the mapping
        expect(getVm(wrapper).buildMapping()).toEqual({ 'idp-admins': ['admin'] });
        expect(wrapper.emitted('update:mapping')).toBeUndefined();
    });

    it('should emit the full mapping when a group name is entered', async () => {
        const wrapper = await createWrapper();

        await wrapper.get('.sw-settings-admin-auth-role-mapping__add-action').trigger('click');

        const vm = getVm(wrapper);
        const newRow = vm.rows[1];
        vm.onGroupChange(newRow, 'idp-catalog');
        vm.onRolesChange(newRow, ['catalog-editor']);

        const emitted = wrapper.emitted('update:mapping');
        expect(emitted?.at(-1)).toEqual([
            {
                'idp-admins': ['admin'],
                'idp-catalog': ['catalog-editor'],
            },
        ]);
    });

    it('should emit the mapping without the removed row', async () => {
        const wrapper = await createWrapper({
            mapping: {
                'idp-admins': ['admin'],
                'idp-catalog': ['catalog-editor'],
            },
        });

        await wrapper.findAll('.sw-settings-admin-auth-role-mapping__remove-action').at(0)!.trigger('click');

        expect(wrapper.emitted('update:mapping')?.at(-1)).toEqual([
            { 'idp-catalog': ['catalog-editor'] },
        ]);
        expect(wrapper.findAll('.sw-settings-admin-auth-role-mapping__row')).toHaveLength(1);
    });

    it('should rebuild the rows when the mapping changes from outside', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({ mapping: { 'idp-support': ['support'] } } as unknown as Parameters<
            typeof wrapper.setProps
        >[0]);

        expect(getVm(wrapper).rows).toHaveLength(1);
        expect(getVm(wrapper).rows[0]).toMatchObject({ group: 'idp-support', roles: ['support'] });
    });

    it('should keep in-progress rows when its own emit echoes back through the prop', async () => {
        const wrapper = await createWrapper();

        await wrapper.get('.sw-settings-admin-auth-role-mapping__add-action').trigger('click');
        expect(getVm(wrapper).rows).toHaveLength(2);

        // parent echoes the emitted mapping back into the prop - the empty row must survive
        await wrapper.setProps({ mapping: { 'idp-admins': ['admin'] } } as unknown as Parameters<
            typeof wrapper.setProps
        >[0]);

        expect(getVm(wrapper).rows).toHaveLength(2);
    });

    it('should disable all inputs and buttons when disabled', async () => {
        const wrapper = await createWrapper({ disabled: true });

        expect(wrapper.get('.sw-settings-admin-auth-role-mapping__add-action').attributes('disabled')).toBeDefined();
        expect(wrapper.get('.sw-settings-admin-auth-role-mapping__remove-action').attributes('disabled')).toBeDefined();
        expect(wrapper.get('.sw-settings-admin-auth-role-mapping__group input').attributes('disabled')).toBeDefined();
    });
});
