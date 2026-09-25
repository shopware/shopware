import axe from 'axe-core';
import Plugin from 'src/plugin-system/plugin.class';

export default class ContentSystemPreviewAccessibilityPlugin extends Plugin {
    init() {
        this._onMessage = this._onMessage.bind(this);
        this._scanInProgress = false;
        window.addEventListener('message', this._onMessage);
    }

    destroy() {
        window.removeEventListener('message', this._onMessage);
    }

    async _onMessage(event) {
        if (event.source !== window.parent) {
            return;
        }

        const parentOrigin = document.referrer ? new URL(document.referrer).origin : null;

        if (parentOrigin && event.origin !== parentOrigin) {
            return;
        }

        const payload = event.data;

        if (
            !payload
            || payload.source !== 'sw-experience-studio-admin'
            || payload.type !== 'accessibility-scan'
            || typeof payload.requestId !== 'number'
        ) {
            return;
        }

        if (this._scanInProgress) {
            return;
        }

        this._scanInProgress = true;

        try {
            const results = await axe.run(document);

            window.parent.postMessage({
                source: 'sw-experience-studio-preview',
                type: 'accessibility-scan-result',
                requestId: payload.requestId,
                violations: results.violations.map((violation) => ({
                    ...violation,
                    nodes: violation.nodes.map((node) => ({
                        ...node,
                        elementId: this._getElementId(node.target),
                    })),
                })),
            }, event.origin);
        } catch {
            window.parent.postMessage({
                source: 'sw-experience-studio-preview',
                type: 'accessibility-scan-result',
                requestId: payload.requestId,
                error: true,
            }, event.origin);
        } finally {
            this._scanInProgress = false;
        }
    }

    _getElementId(target) {
        const selectors = Array.isArray(target) ? target : [target];

        for (const selector of selectors) {
            if (typeof selector !== 'string') {
                continue;
            }

            try {
                const element = document.querySelector(selector);
                const ownerElement = element?.closest('[data-element-id]');

                if (ownerElement) {
                    return ownerElement.getAttribute('data-element-id');
                }
            } catch {
                // Ignore selectors that cannot be queried in the preview document.
            }
        }

        return null;
    }
}
