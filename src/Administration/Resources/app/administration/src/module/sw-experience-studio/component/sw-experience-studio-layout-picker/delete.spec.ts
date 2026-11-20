import { shallowMount } from '@vue/test-utils';
import type { ComponentPublicInstance } from 'vue';
import pickerComponent from './index';

const methods = (pickerComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;

async function createWrapper(allowDelete = true, disabled = false) {
    const [picker, grid, menuItem] = await Promise.all([
        wrapTestComponent('sw-experience-studio-layout-picker', { sync: true }),
        wrapTestComponent('sw-data-grid', { sync: true }),
        wrapTestComponent('sw-context-menu-item', { sync: true }),
    ]);
    const rows = [
        { id: 'layout-a', name: 'Home', version: '1.0.0' },
        { id: 'layout-b', name: 'Summer collection', version: '1.0.0' },
    ];
    const search = jest.fn().mockResolvedValue(Object.assign(rows, { total: 2 }));
    const remove = jest.fn().mockResolvedValue(undefined);
    const wrapper = shallowMount(picker, {
        props: { openLayoutIds: ['layout-a'], disabled },
        global: {
            provide: {
                repositoryFactory: { create: () => ({ search, delete: remove }) },
                acl: { can: (privilege: string) => allowDelete && privilege === 'experience_studio.deleter' },
            },
            stubs: {
                'sw-modal': {
                    name: 'sw-modal',
                    props: ['title', 'closable'],
                    template: '<section><h2>{{ title }}</h2><slot /><footer><slot name="modal-footer" /></footer></section>',
                },
                'sw-data-grid': grid,
                'sw-provide': { inheritAttrs: false, template: '<slot />' },
                'sw-context-button': { template: '<div class="test-row-actions"><slot /></div>' },
                'sw-context-menu-item': menuItem,
                'sw-loader': true,
                'sw-pagination': true,
            },
        },
    });
    await flushPromises();
    return { wrapper, search, remove, rows };
}

describe('layout picker deletion', () => {
    let view: Awaited<ReturnType<typeof createWrapper>>;

    afterEach(() => {
        view?.wrapper.unmount();
    });

    function confirmation() {
        return view.wrapper.get('.sw-experience-studio-layout-picker__delete-modal');
    }

    async function openConfirmation(index = 0) {
        await view.wrapper.findAll('.sw-experience-studio-layout-picker__delete-action')[index].trigger('click');
        await flushPromises();
    }

    it('offers Delete in the grid row actions and requires confirmation before deleting', async () => {
        view = await createWrapper();
        const actions = view.wrapper.findAll('tbody .sw-data-grid__cell--actions');
        expect(actions).toHaveLength(2);
        expect(actions[0].get('.sw-context-menu-item--danger').text()).toBe('global.default.delete');

        await openConfirmation();
        expect(confirmation().get('h2').text()).toBe('sw-experience-studio.workspace.layoutPicker.deleteTitle');
        expect(view.remove).not.toHaveBeenCalled();
        expect(view.wrapper.emitted('select')).toBeUndefined();
        view.search.mockResolvedValueOnce(Object.assign([view.rows[1]], { total: 1 }));
        await confirmation().get('.mt-button--critical').trigger('click');
        await flushPromises();

        expect(view.remove).toHaveBeenCalledWith('layout-a', Shopware.Context.api);
        expect(view.wrapper.emitted('delete')).toEqual([['layout-a']]);
        expect(view.wrapper.emitted('select')).toBeUndefined();
        expect(view.wrapper.find('.sw-experience-studio-layout-picker__delete-modal').exists()).toBe(false);
        expect(view.wrapper.findAll('.sw-experience-studio-layout-picker__layout-name').map((item) => item.text())).toEqual([
            'Summer collection',
        ]);
    });

    it('cancels deletion without deleting or closing the picker', async () => {
        view = await createWrapper();
        await openConfirmation();
        await confirmation().get('.mt-button--secondary').trigger('click');

        expect(view.remove).not.toHaveBeenCalled();
        expect(view.wrapper.emitted('delete')).toBeUndefined();
        expect(view.wrapper.emitted('close')).toBeUndefined();
        expect(view.wrapper.find('.sw-experience-studio-layout-picker__delete-modal').exists()).toBe(false);
    });

    it('hides deletion without the deleter privilege and guards direct confirmation calls', async () => {
        view = await createWrapper(false);
        expect(view.wrapper.find('.sw-data-grid__cell--actions').exists()).toBe(false);
        const editor = {
            deleteActionsDisabled: true,
            layoutToDelete: view.rows[0],
            layoutRepository: { delete: view.remove },
        };
        methods.onDeleteLayout.call(editor, view.rows[0]);
        await methods.onConfirmDeleteLayout.call(editor);
        expect(view.remove).not.toHaveBeenCalled();
    });

    it('blocks deletion while the canvas is saving or mutating layouts', async () => {
        view = await createWrapper(true, true);
        await openConfirmation();

        expect(view.wrapper.find('.sw-experience-studio-layout-picker__delete-modal').exists()).toBe(false);
        expect(view.remove).not.toHaveBeenCalled();
    });

    it('keeps failed deletions open for retry and does not close the layout frame', async () => {
        view = await createWrapper();
        view.remove.mockRejectedValueOnce(new Error('Layout is assigned'));
        await openConfirmation();
        await confirmation().get('.mt-button--critical').trigger('click');
        await flushPromises();

        expect(confirmation().text()).toContain('sw-experience-studio.workspace.layoutPicker.deleteError');
        expect(view.wrapper.emitted('delete')).toBeUndefined();
        expect(view.search).toHaveBeenCalledTimes(1);
        view.search.mockResolvedValueOnce(Object.assign([view.rows[1]], { total: 1 }));
        await confirmation().get('.mt-button--critical').trigger('click');
        await flushPromises();
        expect(view.remove).toHaveBeenCalledTimes(2);
        expect(view.wrapper.emitted('delete')).toEqual([['layout-a']]);
    });

    it('returns to the previous page when deleting the only layout on a later page', async () => {
        view = await createWrapper();
        await view.wrapper.setData({ page: 2, layouts: [view.rows[1]], total: 26 });
        await openConfirmation();
        await confirmation().get('.mt-button--critical').trigger('click');
        await flushPromises();

        expect(view.remove).toHaveBeenCalledWith('layout-b', Shopware.Context.api);
        expect(view.search).toHaveBeenLastCalledWith(expect.objectContaining({ page: 1, limit: 25 }), Shopware.Context.api);
    });

    it('prevents duplicate deletion and closing the picker while deletion is in flight', async () => {
        view = await createWrapper();
        let finishDelete!: () => void;
        view.remove.mockReturnValueOnce(
            new Promise<void>((resolve) => {
                finishDelete = resolve;
            }),
        );
        await openConfirmation();
        await confirmation().get('.mt-button--critical').trigger('click');
        await confirmation().get('.mt-button--critical').trigger('click');
        view.wrapper.getComponent<ComponentPublicInstance>({ name: 'sw-modal' }).vm.$emit('modal-close');
        await flushPromises();
        expect(view.remove).toHaveBeenCalledTimes(1);
        expect(view.wrapper.emitted('close')).toBeUndefined();
        expect(confirmation().get('.mt-button--secondary').attributes('disabled')).toBeDefined();

        finishDelete();
        await flushPromises();
        expect(view.wrapper.emitted('delete')).toEqual([['layout-a']]);
    });
});
