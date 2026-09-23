import pageSettingsComponent from './index';

describe('module/sw-experience-studio/component/sw-experience-studio-page-settings', () => {
    const computed = (
        pageSettingsComponent as unknown as {
            computed: Record<string, (...args: unknown[]) => unknown>;
        }
    ).computed;
    const methods = (
        pageSettingsComponent as unknown as {
            methods: Record<string, (...args: unknown[]) => unknown>;
        }
    ).methods;

    const storedSettings = {
        scrollNavigation: {
            active: true,
            mode: 'sidebar',
            position: 'left',
            anchors: { 'element-a': { label: 'Intro' } },
        },
    };

    function emitter(scrollNavigation: Record<string, unknown>, allowEdit = true) {
        const $emit = jest.fn();

        return {
            $emit,
            context: {
                allowEdit,
                scrollNavigation,
                emitScrollNavigation: methods.emitScrollNavigation,
                $emit,
            },
        };
    }

    it('falls back to an inactive right-hand sidebar without stored settings', () => {
        const expected = { active: false, mode: 'sidebar', position: 'right', anchors: {} };

        expect(computed.scrollNavigation.call({ layout: { settings: null } })).toEqual(expected);
        expect(computed.scrollNavigation.call({ layout: null })).toEqual(expected);
    });

    it('counts only anchors whose element still exists in the tree', () => {
        const layout = {
            settings: {
                scrollNavigation: {
                    ...storedSettings.scrollNavigation,
                    anchors: {
                        'element-a': { label: 'Intro' },
                        'element-nested': { label: 'Nested' },
                        'element-deleted': { label: 'Gone' },
                    },
                },
            },
            layout: [
                { id: 'element-a', component: 'Sw:Grid:Container', slots: { content: [{ id: 'element-nested', component: 'Sw:Content:Text' }] } },
            ],
        };
        const scrollNavigation = computed.scrollNavigation.call({ layout });

        expect(computed.anchorCount.call({ scrollNavigation, layout })).toBe(2);
    });

    it('offers the position only for an active sidebar', () => {
        expect(computed.showPositionOption.call({ scrollNavigation: { active: true, mode: 'sidebar' } })).toBe(true);
        expect(computed.showPositionOption.call({ scrollNavigation: { active: true, mode: 'flat' } })).toBe(false);
        expect(computed.showPositionOption.call({ scrollNavigation: { active: false, mode: 'sidebar' } })).toBe(false);
    });

    it('emits the merged scroll navigation settings and keeps the anchors when toggled', () => {
        const { $emit, context } = emitter(storedSettings.scrollNavigation);

        methods.onScrollNavigationActiveChange.call(context, false);

        expect($emit).toHaveBeenCalledWith('update-settings', {
            settings: {
                scrollNavigation: {
                    active: false,
                    mode: 'sidebar',
                    position: 'left',
                    anchors: { 'element-a': { label: 'Intro' } },
                },
            },
        });
    });

    it('emits a presentation change and normalises unknown modes to the sidebar', () => {
        const { $emit, context } = emitter({ active: true, mode: 'sidebar', position: 'right', anchors: {} });

        methods.onScrollNavigationModeChange.call(context, 'flat');
        methods.onScrollNavigationModeChange.call(context, 'tree');

        expect($emit).toHaveBeenNthCalledWith(1, 'update-settings', {
            settings: { scrollNavigation: { active: true, mode: 'flat', position: 'right', anchors: {} } },
        });
        expect($emit).toHaveBeenNthCalledWith(2, 'update-settings', {
            settings: { scrollNavigation: { active: true, mode: 'sidebar', position: 'right', anchors: {} } },
        });
    });

    it('emits a position change and keeps the active flag', () => {
        const { $emit, context } = emitter({ active: true, mode: 'sidebar', position: 'right', anchors: {} });

        methods.onScrollNavigationPositionChange.call(context, 'left');

        expect($emit).toHaveBeenCalledWith('update-settings', {
            settings: { scrollNavigation: { active: true, mode: 'sidebar', position: 'left', anchors: {} } },
        });
    });

    it('does not emit without edit permission', () => {
        const { $emit, context } = emitter({ active: false, mode: 'sidebar', position: 'right', anchors: {} }, false);

        methods.onScrollNavigationActiveChange.call(context, true);

        expect($emit).not.toHaveBeenCalled();
    });
});
