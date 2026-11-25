import { canvasPanelHandlers } from './canvas-panel-handlers';

describe('canvas settings panel', () => {
    it('can open a layout-wide panel without navigating the left sidebar to the structure', () => {
        const vm = {
            activePanel: 'layouts' as const,
            isSettingsOpen: false,
            focusFrame: jest.fn(),
            closeSettingsPanel: jest.fn(),
            $emit: jest.fn(),
        };
        canvasPanelHandlers.openSettingsPanel.call(vm, false);

        expect(vm.activePanel).toBe('layouts');
        expect(vm.isSettingsOpen).toBe(true);
        expect(vm.$emit).toHaveBeenCalledWith('settings-open');
    });
});
