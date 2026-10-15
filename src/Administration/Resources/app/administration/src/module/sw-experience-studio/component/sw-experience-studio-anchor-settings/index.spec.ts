import anchorSettingsComponent from './index';

describe('module/sw-experience-studio/component/sw-experience-studio-anchor-settings', () => {
    const computed = (
        anchorSettingsComponent as unknown as {
            computed: Record<string, (...args: unknown[]) => unknown>;
        }
    ).computed;
    const methods = (
        anchorSettingsComponent as unknown as {
            methods: Record<string, (...args: unknown[]) => unknown>;
        }
    ).methods;

    const scrollNavigation = {
        active: true,
        position: 'right',
        anchors: { 'element-a': { label: 'Intro' } },
    };

    it('resolves the anchor of the selected element', () => {
        expect(computed.anchor.call({ scrollNavigation, elementId: 'element-a' })).toEqual({ label: 'Intro' });
        expect(computed.anchor.call({ scrollNavigation, elementId: 'element-b' })).toBeNull();
    });

    it('flags an anchor whose page navigation is switched off', () => {
        expect(computed.isNavigationInactive.call({ isAnchor: true, scrollNavigation: { active: false } })).toBe(true);
        expect(computed.isNavigationInactive.call({ isAnchor: true, scrollNavigation: { active: true } })).toBe(false);
        expect(computed.isNavigationInactive.call({ isAnchor: false, scrollNavigation: { active: false } })).toBe(false);
    });

    it('adds the selected element to the anchors when enabled', () => {
        const $emit = jest.fn();

        methods.onAnchorToggle.call(
            {
                allowEdit: true,
                elementId: 'element-b',
                anchorLabel: '',
                scrollNavigation,
                emitAnchors: methods.emitAnchors,
                $emit,
            },
            true,
        );

        expect($emit).toHaveBeenCalledWith('update-settings', {
            settings: {
                scrollNavigation: {
                    active: true,
                    position: 'right',
                    anchors: {
                        'element-a': { label: 'Intro' },
                        'element-b': { label: '' },
                    },
                },
            },
        });
    });

    it('removes the selected element from the anchors when disabled', () => {
        const $emit = jest.fn();

        methods.onAnchorToggle.call(
            {
                allowEdit: true,
                elementId: 'element-a',
                anchorLabel: 'Intro',
                scrollNavigation,
                emitAnchors: methods.emitAnchors,
                $emit,
            },
            false,
        );

        expect($emit).toHaveBeenCalledWith('update-settings', {
            settings: {
                scrollNavigation: { active: true, position: 'right', anchors: {} },
            },
        });
    });

    it('updates the label of an existing anchor only', () => {
        const $emit = jest.fn();
        const context = {
            allowEdit: true,
            elementId: 'element-a',
            isAnchor: true,
            scrollNavigation,
            emitAnchors: methods.emitAnchors,
            $emit,
        };

        methods.onAnchorLabelChange.call(context, 'Welcome');

        expect($emit).toHaveBeenCalledWith('update-settings', {
            settings: {
                scrollNavigation: {
                    active: true,
                    position: 'right',
                    anchors: { 'element-a': { label: 'Welcome' } },
                },
            },
        });

        $emit.mockClear();
        methods.onAnchorLabelChange.call({ ...context, isAnchor: false }, 'Ignored');

        expect($emit).not.toHaveBeenCalled();
    });

    it('does not emit without edit permission', () => {
        const $emit = jest.fn();

        methods.onAnchorToggle.call(
            {
                allowEdit: false,
                elementId: 'element-b',
                anchorLabel: '',
                scrollNavigation,
                emitAnchors: methods.emitAnchors,
                $emit,
            },
            true,
        );

        expect($emit).not.toHaveBeenCalled();
    });
});
