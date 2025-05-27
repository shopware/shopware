import type { MediaModalConfig } from 'src/app/store/media-modal.store';
import template from './sw-media-modal-renderer.html.twig';

/**
 * @sw-package framework
 *
 * @private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
    template,

    computed: {
        mediaModal(): MediaModalConfig | null {
            return Shopware.Store.get('mediaModal').mediaModal;
        },
    },

    methods: {
        closeModal(): void {
            Shopware.Store.get('mediaModal').closeModal();
        },

        onSelectionChange(selection: EntityCollection<'media'>): void {
            // eslint-disable-next-line @typescript-eslint/no-unnecessary-type-assertion
            const selectors: string[] = (this.mediaModal?.selectors as string[] | undefined) || [
                'id',
                'fileName',
                'url',
            ];

            const mediaSelection = this.transformObjectsByPaths(selection, selectors);

            if (this.mediaModal && typeof this.mediaModal.callback === 'function') {
                const callbackFn = this.mediaModal.callback as (selection: Array<Record<string, unknown>>) => void;
                callbackFn(mediaSelection);
            }
        },

        getValueByPath(obj: unknown, path: string): unknown {
            if (typeof path !== 'string' || path.length === 0) {
                return undefined;
            }

            const parts = path.split('.');

            return parts.reduce((currentAccumulator: unknown, currentKey: string): unknown => {
                if (currentAccumulator && typeof currentAccumulator === 'object' && currentKey in currentAccumulator) {
                    return (currentAccumulator as Record<string, unknown>)[currentKey];
                }
                return undefined;
            }, obj);
        },

        transformObjectsByPaths(inputArray: Entity<'media'>[], keysToKeep: string[]): Array<Record<string, unknown>> {
            if (!Array.isArray(inputArray) || !Array.isArray(keysToKeep)) {
                return [];
            }

            return inputArray.map((item) => {
                const transformedObject = {};

                keysToKeep
                    .filter((keyPath) => typeof keyPath === 'string' && keyPath.length > 0) // 1. Filter for valid keyPaths
                    .forEach((keyPath: string) => {
                        const value = this.getValueByPath(item, keyPath);
                        this.setValueByPath(transformedObject, keyPath, value);
                    });

                return transformedObject;
            });
        },

        setValueByPath(obj: unknown, path: string, value: unknown): void {
            if (typeof path !== 'string' || path.length === 0 || typeof obj !== 'object' || obj === null) {
                return;
            }

            const parts = path.split('.');
            let currentContext: Record<string, unknown> = obj as Record<string, unknown>;

            const intermediateParts = parts.slice(0, -1);

            intermediateParts.forEach((pathSegment: string) => {
                const segmentValue: unknown = currentContext[pathSegment];

                if (!(segmentValue && typeof segmentValue === 'object')) {
                    currentContext[pathSegment] = {};
                }
                // Update currentContext to point to the (potentially newly created) nested object.
                currentContext = currentContext[pathSegment] as Record<string, unknown>;
            });

            const finalSegment = parts[parts.length - 1];
            currentContext[finalSegment] = value;
        },
    },
});
