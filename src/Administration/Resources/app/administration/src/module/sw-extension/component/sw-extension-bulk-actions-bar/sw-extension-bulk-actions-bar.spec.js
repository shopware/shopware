import { mount } from '@vue/test-utils';

function counts(overrides = {}) {
    return {
        install: 1,
        activate: 1,
        deactivate: 1,
        update: 1,
        uninstall: 1,
        ...overrides,
    };
}

async function createWrapper(props = {}) {
    return mount(
        await wrapTestComponent('sw-extension-bulk-actions-bar', {
            sync: true,
        }),
        {
            props: {
                selectedCount: 3,
                applicableCounts: counts(),
                disabled: false,
                ...props,
            },
            global: {
                stubs: {
                    'router-link': true,
                },
            },
        },
    );
}

const ACTION_ORDER = [
    'install',
    'activate',
    'deactivate',
    'update',
    'uninstall',
];

function findActionAnchors(wrapper) {
    return wrapper.findAll('.sw-extension-bulk-actions-bar__actions .sw-extension-bulk-actions-bar__action');
}

/**
 * @sw-package checkout
 */
describe('src/module/sw-extension/component/sw-extension-bulk-actions-bar', () => {
    it('should render the selected count', async () => {
        const wrapper = await createWrapper({ selectedCount: 7 });

        expect(wrapper.find('.sw-extension-bulk-actions-bar__count').text()).toBe('7');
    });

    it('should emit select-all when the select-all action is clicked', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-extension-bulk-actions-bar__select-all').trigger('click');

        expect(wrapper.emitted('select-all')).toHaveLength(1);
    });

    it('should emit clear when the deselect action is clicked', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-extension-bulk-actions-bar__deselect').trigger('click');

        expect(wrapper.emitted('clear')).toHaveLength(1);
    });

    it.each([
        [
            0,
            'install',
        ],
        [
            1,
            'activate',
        ],
        [
            2,
            'deactivate',
        ],
        [
            3,
            'update',
        ],
        [
            4,
            'uninstall',
        ],
    ])('should emit run-action for the action at index %i (%s)', async (index, action) => {
        const wrapper = await createWrapper();

        await findActionAnchors(wrapper).at(index).trigger('click');

        expect(wrapper.emitted('run-action')[0]).toEqual([action]);
    });

    it('should not emit run-action when the action count is zero', async () => {
        const wrapper = await createWrapper({ applicableCounts: counts({ install: 0 }) });

        await findActionAnchors(wrapper).at(0).trigger('click');

        expect(wrapper.emitted('run-action')).toBeUndefined();
    });

    it('should not emit run-action when disabled', async () => {
        const wrapper = await createWrapper({ disabled: true });

        await findActionAnchors(wrapper).at(1).trigger('click');

        expect(wrapper.emitted('run-action')).toBeUndefined();
    });

    it('should mark an action disabled when its applicable count is zero', async () => {
        const wrapper = await createWrapper({
            applicableCounts: counts({ install: 0, deactivate: 0, update: 0, activate: 2, uninstall: 3 }),
        });

        const anchors = findActionAnchors(wrapper);
        const disabledByAction = ACTION_ORDER.reduce((map, action, index) => {
            map[action] = anchors.at(index).classes('is--disabled');

            return map;
        }, {});

        expect(disabledByAction).toEqual({
            install: true,
            activate: false,
            deactivate: true,
            update: true,
            uninstall: false,
        });
    });

    it('should mark all actions disabled when the disabled prop is true', async () => {
        const wrapper = await createWrapper({
            disabled: true,
            applicableCounts: counts({ install: 5, activate: 5, deactivate: 5, update: 5, uninstall: 5 }),
        });

        findActionAnchors(wrapper).forEach((anchor) => {
            expect(anchor.classes()).toContain('is--disabled');
        });
    });
});
