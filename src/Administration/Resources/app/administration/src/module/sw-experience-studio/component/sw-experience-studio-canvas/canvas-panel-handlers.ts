type CanvasPanelContext = {
    activePanel: 'structure' | 'layouts';
    isSettingsOpen: boolean;
    focusFrame: (frameId: string) => void;
    closeSettingsPanel: () => void;
    $emit: (event: 'settings-open' | 'settings-close') => void;
};

/** @private @sw-package discovery */
export const canvasPanelHandlers = {
    openStructure(this: CanvasPanelContext, frameId: string): void {
        this.focusFrame(frameId);
        this.activePanel = 'structure';
    },

    openSettingsPanel(this: CanvasPanelContext, showStructure = true): void {
        if (showStructure) {
            this.activePanel = 'structure';
        }
        if (!this.isSettingsOpen) {
            this.isSettingsOpen = true;
            this.$emit('settings-open');
        }
    },

    closeStructurePanel(this: CanvasPanelContext): void {
        this.activePanel = 'layouts';
    },

    closeSettingsPanel(this: CanvasPanelContext): void {
        if (!this.isSettingsOpen) {
            return;
        }
        this.isSettingsOpen = false;
        this.$emit('settings-close');
    },

    closePanel(this: CanvasPanelContext): void {
        this.activePanel = 'layouts';
        this.closeSettingsPanel();
    },
};
