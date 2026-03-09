import template from './sw-content-system-index.html.twig';
import './sw-content-system-index.scss';

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
    template,

    data() {
        return {
            landingPageId: '7c6c8f28f6c549ecb94a6a1760d2f5c1',
            previewPath: '/content-system/demo/',
            refreshNonce: Date.now(),
            isTreeLoading: false,
            movingElementId: null,
            previewReady: false,
            treeError: null,
            layoutMeta: null,
            layoutTreeRows: [],
            selectedElementId: null,
        };
    },

    computed: {
        previewUrl() {
            const origin = window.location.origin.replace(/\/$/, '');
            const path = this.previewPath.replace(/\/?$/, '/');
            const landingPageId = this.landingPageId.trim();

            return `${origin}${path}${landingPageId}?_preview=${this.refreshNonce}`;
        },

        layoutTreeUrl() {
            const origin = window.location.origin.replace(/\/$/, '');
            const landingPageId = this.landingPageId.trim();

            return `${origin}/content-system/demo/layout/${landingPageId}?_preview=${this.refreshNonce}`;
        },

        layoutTreeMoveUrl() {
            const origin = window.location.origin.replace(/\/$/, '');
            const landingPageId = this.landingPageId.trim();

            return `${origin}/content-system/demo/layout/${landingPageId}/move`;
        },
    },

    created() {
        window.addEventListener('message', this.onPreviewMessage);
        this.loadLayoutTree();
    },

    beforeUnmount() {
        window.removeEventListener('message', this.onPreviewMessage);
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    methods: {
        refreshPreview() {
            this.previewReady = false;
            this.refreshNonce = Date.now();
            this.loadLayoutTree();
        },

        openPreviewInNewTab() {
            window.open(this.previewUrl, '_blank', 'noopener');
        },

        onPreviewMessage(event) {
            const payload = event?.data;

            if (!payload || typeof payload !== 'object') {
                return;
            }

            if (event.origin !== window.location.origin) {
                return;
            }

            if (payload.type === 'content-system:refresh-preview') {
                this.refreshPreview();

                return;
            }

            if (payload.type === 'content-system:preview-ready') {
                this.previewReady = true;
                this.syncSelectionToPreview();

                return;
            }

            if (payload.type === 'content-system:element-selected' && typeof payload.elementId === 'string') {
                this.selectedElementId = payload.elementId;
            }
        },

        async loadLayoutTree() {
            const landingPageId = this.landingPageId.trim();

            if (!landingPageId) {
                this.layoutMeta = null;
                this.layoutTreeRows = [];
                this.treeError = null;

                return;
            }

            this.isTreeLoading = true;
            this.treeError = null;

            try {
                const response = await fetch(this.layoutTreeUrl, {
                    headers: {
                        Accept: 'application/json',
                    },
                });

                const result = await response.json();
                const payload = result?.payload && typeof result.payload === 'object' ? result.payload : null;
                const endpoint = typeof result?.endpoint === 'string' ? result.endpoint : this.layoutTreeUrl;
                const error = typeof result?.error === 'string' ? result.error : null;

                if (!response.ok || error) {
                    throw new Error(error || `Failed to load layout tree (${response.status})`);
                }

                this.layoutMeta = {
                    endpoint,
                    layoutId: payload?.layoutId ?? null,
                    layoutName: payload?.layoutName ?? null,
                };
                this.layoutTreeRows = this.buildTreeRows(payload);
                this.ensureSelectedElementStillExists();
            } catch (error) {
                this.layoutMeta = null;
                this.layoutTreeRows = [];
                this.treeError = error instanceof Error ? error.message : String(error);
            } finally {
                this.isTreeLoading = false;
            }
        },

        buildTreeRows(payload) {
            const rows = [];

            if (!payload || typeof payload !== 'object') {
                return rows;
            }

            const layoutName = typeof payload.layoutName === 'string' ? payload.layoutName : 'Unnamed layout';
            const layoutId = typeof payload.layoutId === 'string' ? payload.layoutId : 'n/a';

            rows.push({
                key: `layout-${layoutId}`,
                depth: 0,
                kind: 'layout',
                label: layoutName,
                component: 'Layout',
                elementId: null,
            });

            if (!Array.isArray(payload.elements)) {
                return rows;
            }

            payload.elements.forEach((element, index) => {
                this.appendElementRows(rows, element, 1, `e-${index}`, [], index, payload.elements.length);
            });

            return rows;
        },

        appendElementRows(rows, element, depth, keyPrefix, slotPath, slotIndex, siblingCount) {
            if (!element || typeof element !== 'object') {
                return;
            }

            const elementId = typeof element.id === 'string' ? element.id : `${keyPrefix}-id`;
            const componentName = typeof element.component === 'string' ? element.component : 'Unknown';
            const elementPath = [
                ...slotPath,
                slotIndex,
            ];

            rows.push({
                key: `element-${keyPrefix}-${elementId}`,
                depth,
                kind: 'element',
                label: componentName,
                component: componentName,
                elementId,
                slotPath,
                slotIndex,
                canMoveUp: slotIndex > 0,
                canMoveDown: slotIndex < siblingCount - 1,
            });

            if (!element.slots || typeof element.slots !== 'object') {
                return;
            }

            Object.entries(element.slots).forEach(
                ([
                    slotName,
                    slotEntries,
                ]) => {
                    if (!slotEntries || typeof slotEntries !== 'object') {
                        return;
                    }

                    rows.push({
                        key: `slot-${keyPrefix}-${slotName}`,
                        depth: depth + 1,
                        kind: 'slot',
                        label: slotName,
                        component: 'Slot',
                        elementId: null,
                    });

                    const children = this.normalizeSlotEntries(slotEntries);
                    const childSlotPath = [
                        ...elementPath,
                        'slots',
                        slotName,
                    ];

                    children.forEach((child, childIndex) => {
                        this.appendElementRows(
                            rows,
                            child,
                            depth + 2,
                            `${keyPrefix}-${slotName}-${childIndex}`,
                            childSlotPath,
                            childIndex,
                            children.length,
                        );
                    });
                },
            );
        },

        normalizeSlotEntries(slotEntries) {
            return Object.entries(slotEntries)
                .filter(
                    ([
                        key,
                        value,
                    ]) => key !== 'apiAlias' && value && typeof value === 'object',
                )
                .map(
                    ([
                        ,
                        value,
                    ]) => value,
                );
        },

        selectTreeRow(row) {
            if (!row || row.kind !== 'element' || typeof row.elementId !== 'string') {
                return;
            }

            this.selectedElementId = row.elementId;
            this.syncSelectionToPreview();
        },

        isTreeRowSelected(row) {
            return row.kind === 'element' && row.elementId === this.selectedElementId;
        },

        isTreeRowMovePending(row) {
            return row.kind === 'element' && row.elementId === this.movingElementId;
        },

        async moveTreeRow(row, direction) {
            if (!row || row.kind !== 'element' || !Array.isArray(row.slotPath) || typeof row.slotIndex !== 'number') {
                return;
            }

            if (this.movingElementId) {
                return;
            }

            this.movingElementId = row.elementId;
            this.treeError = null;

            try {
                const response = await fetch(this.layoutTreeMoveUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        slotPath: row.slotPath,
                        fromIndex: row.slotIndex,
                        direction,
                    }),
                });

                const result = await response.json();
                const error = typeof result?.error === 'string' ? result.error : null;

                if (!response.ok || error) {
                    throw new Error(error || `Failed to move layout element (${response.status})`);
                }

                if (typeof result?.selectedElementId === 'string') {
                    this.selectedElementId = result.selectedElementId;
                }

                this.refreshPreview();
            } catch (error) {
                this.treeError = error instanceof Error ? error.message : String(error);
            } finally {
                this.movingElementId = null;
            }
        },

        syncSelectionToPreview() {
            if (!this.previewReady || !this.selectedElementId) {
                return;
            }

            const iframe = this.$refs.previewFrame;
            const contentWindow = iframe?.contentWindow;

            if (!contentWindow) {
                return;
            }

            contentWindow.postMessage(
                {
                    type: 'content-system:select-element',
                    elementId: this.selectedElementId,
                },
                window.location.origin,
            );
        },

        ensureSelectedElementStillExists() {
            if (!this.selectedElementId) {
                return;
            }

            const hasSelectedElement = this.layoutTreeRows.some((row) => row.elementId === this.selectedElementId);

            if (!hasSelectedElement) {
                this.selectedElementId = null;
            }
        },
    },
});
