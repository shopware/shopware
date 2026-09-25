import type { ContentElementNode } from 'src/core/service/content-element.types';
import { getContentElementLabel } from '../../util/content-element-label.util';
import type { ExperienceStudioElementTypeStore } from '../../store/experience-studio-element-type.store';
import type { AccessibilityViolation } from '../../util/accessibility.types';
import type { AccessibilityFixProposal } from '../../util/accessibility-fix.types';
import { requestAccessibilityFix } from '../../util/accessibility-fix.service';
import type { ContentSystemElementTypeSpecification } from 'src/core/service/api/content-system-element-type.api.service';

import template from './sw-experience-studio-sidebar-tree-node.html.twig';
import './sw-experience-studio-sidebar-tree-node.scss';

type MoveElementPayload = {
    elementId: string;
    newParentElementId: string | null;
    newSlotName: string | null;
    newIndex: number | null;
};

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    name: 'sw-experience-studio-sidebar-tree-node',

    constants: {
        DRAG_GROUP: 'experience-studio-sidebar-tree',
    },

    inject: [
        'acl',
    ],

    props: {
        element: {
            type: Object as PropType<ContentElementNode>,
            required: true,
        },
        selectedElementId: {
            type: String,
            required: false,
            default: null,
        },
        depth: {
            type: Number,
            required: false,
            default: 0,
        },
        allowDragAndDrop: {
            type: Boolean,
            required: false,
            default: false,
        },
        validateMoveTarget: {
            type: Function as unknown as PropType<((payload: MoveElementPayload) => boolean) | null>,
            required: false,
            default: null,
        },
        parentElementId: {
            type: String,
            required: false,
            default: null,
        },
        parentSlotName: {
            type: String,
            required: false,
            default: null,
        },
        indexInParent: {
            type: Number,
            required: false,
            default: null,
        },
        accessibilityViolations: {
            type: Array as PropType<AccessibilityViolation[]>,
            required: false,
            default: () => [],
        },
    },

    emits: [
        'select-element',
        'add-element',
        'duplicate-element',
        'delete-element',
        'move-element',
        'accessibility-fix-apply',
    ],

    data(): {
        isExpanded: boolean;
        showAccessibilityModal: boolean;
        isAccessibilityFixLoading: boolean;
        accessibilityFixError: string | null;
        accessibilityFixProposal: AccessibilityFixProposal | null;
        accessibilityFixElementSnapshot: string | null;
    } {
        return {
            isExpanded: true,
            showAccessibilityModal: false,
            isAccessibilityFixLoading: false,
            accessibilityFixError: null,
            accessibilityFixProposal: null,
            accessibilityFixElementSnapshot: null,
        };
    },

    computed: {
        contentElement(): ContentElementNode {
            return this.element;
        },

        elementTypeStore() {
            return Shopware.Store.get('experienceStudioElementType' as never) as ExperienceStudioElementTypeStore;
        },

        elementTypeSpecification(): ContentSystemElementTypeSpecification | null {
            return this.elementTypeStore.getByName(this.contentElement.component);
        },

        label(): string {
            return getContentElementLabel(this.contentElement);
        },

        typeIcon(): string {
            const configuredIcon = this.elementTypeStore.getByName(this.contentElement.component)?.icon;

            return configuredIcon && configuredIcon.length > 0 ? configuredIcon : 'bars-square-s';
        },

        slotEntries(): Array<{ name: string; elements: ContentElementNode[] }> {
            const slots = this.contentElement.slots ?? {};
            const definedSlots = this.elementTypeStore.getByName(this.contentElement.component)?.slots ?? [];
            const slotNames = Array.from(
                new Set([
                    ...definedSlots.map((slot) => slot.name),
                    ...Object.keys(slots),
                ]),
            );

            return slotNames.map((name) => ({
                name,
                elements: Array.isArray(slots[name]) ? slots[name] : [],
            }));
        },

        hasSlots(): boolean {
            return this.slotEntries.length > 0;
        },

        isSelected(): boolean {
            return this.selectedElementId === this.contentElement.id;
        },

        elementAccessibilityViolations(): AccessibilityViolation[] {
            return this.accessibilityViolations
                .map((violation) => ({
                    ...violation,
                    nodes: violation.nodes.filter((node) => node.elementId === this.contentElement.id),
                }))
                .filter((violation) => violation.nodes.length > 0);
        },

        allowEdit(): boolean {
            return this.acl.can('experience_studio.editor');
        },
    },

    methods: {
        preventDrag(event: Event): void {
            event.stopPropagation();
        },

        onSelectElement(): void {
            this.$emit('select-element', this.contentElement.id);
        },

        onToggleExpand(): void {
            if (!this.hasSlots) {
                return;
            }

            this.isExpanded = !this.isExpanded;
        },

        onAddElement(slotName: string, event: MouseEvent): void {
            this.$emit('add-element', {
                parentElementId: this.contentElement.id,
                slotName,
                anchorElement: event.currentTarget as HTMLElement | null,
            });
        },

        onDuplicateElement(): void {
            this.$emit('duplicate-element', this.contentElement.id);
        },

        onDeleteElement(): void {
            this.$emit('delete-element', this.contentElement.id);
        },

        onOpenAccessibilityModal(): void {
            this.showAccessibilityModal = true;
        },

        onCloseAccessibilityModal(): void {
            this.showAccessibilityModal = false;
            this.accessibilityFixProposal = null;
            this.accessibilityFixElementSnapshot = null;
            this.accessibilityFixError = null;
        },

        async onRequestAccessibilityFix(): Promise<void> {
            this.isAccessibilityFixLoading = true;
            this.accessibilityFixError = null;
            this.accessibilityFixProposal = null;
            this.accessibilityFixElementSnapshot = JSON.stringify(this.contentElement);

            try {
                this.accessibilityFixProposal = await requestAccessibilityFix(
                    this.contentElement,
                    this.elementAccessibilityViolations,
                    this.elementTypeSpecification,
                );
            } catch {
                this.accessibilityFixError = 'sw-experience-studio.detail.sidebarTree.accessibilityFixError';
            } finally {
                this.isAccessibilityFixLoading = false;
            }
        },

        onApplyAccessibilityFix(): void {
            if (!this.accessibilityFixProposal || this.accessibilityFixProposal.operations.length === 0) {
                return;
            }

            if (this.accessibilityFixElementSnapshot !== JSON.stringify(this.contentElement)) {
                this.accessibilityFixError = 'sw-experience-studio.detail.sidebarTree.accessibilityFixStale';

                return;
            }

            this.$emit('accessibility-fix-apply', this.accessibilityFixProposal.operations);
            this.showAccessibilityModal = false;
            this.accessibilityFixProposal = null;
            this.accessibilityFixElementSnapshot = null;
        },

        onCancelAccessibilityFix(): void {
            this.accessibilityFixProposal = null;
            this.accessibilityFixElementSnapshot = null;
            this.accessibilityFixError = null;
        },

        getAccessibilityFixCurrentValue(operation: AccessibilityFixProposal['operations'][number]): unknown {
            const source = operation.type.includes('property') ? this.contentElement.properties : this.contentElement.style;

            return operation.path.split('.').reduce<unknown>((value, pathPart) => {
                if (typeof value !== 'object' || value === null || Array.isArray(value)) {
                    return undefined;
                }

                return (value as Record<string, unknown>)[pathPart];
            }, source);
        },

        formatAccessibilityFixValue(value: unknown): string {
            if (value === undefined) {
                return '—';
            }

            return JSON.stringify(value) ?? '—';
        },

        collectSubtreeIds(element: ContentElementNode): string[] {
            const nestedSlotElements = Object.values(element.slots ?? {}).flatMap((slotElements) => slotElements);
            const nestedIds = nestedSlotElements.flatMap((childElement) => this.collectSubtreeIds(childElement));

            return [
                element.id,
                ...nestedIds,
            ];
        },

        dragConfig() {
            return {
                dragGroup: (this.$options.constants as { DRAG_GROUP: string }).DRAG_GROUP,
                disabled: !this.allowDragAndDrop,
                data: {
                    elementId: this.contentElement.id,
                    elementComponent: this.contentElement.component,
                    subtreeIds: this.collectSubtreeIds(this.contentElement),
                },
                // eslint-disable-next-line @typescript-eslint/unbound-method
                onDrop: this.onDropElement,
            };
        },

        dropConfigForSlot(slotName: string) {
            return {
                dragGroup: (this.$options.constants as { DRAG_GROUP: string }).DRAG_GROUP,
                data: {
                    newParentElementId: this.contentElement.id,
                    newSlotName: slotName,
                    newIndex: null,
                },
                // eslint-disable-next-line @typescript-eslint/unbound-method
                validateDrop: this.validateMoveDrop,
            };
        },

        dropConfigForElement() {
            return {
                dragGroup: (this.$options.constants as { DRAG_GROUP: string }).DRAG_GROUP,
                data: {
                    newParentElementId: this.parentElementId,
                    newSlotName: this.parentSlotName,
                    newIndex: this.indexInParent,
                },
                // eslint-disable-next-line @typescript-eslint/unbound-method
                validateDrop: this.validateMoveDrop,
            };
        },

        validateMoveDrop(
            dragData: { elementId: string; subtreeIds: string[] } | null,
            dropData: Omit<MoveElementPayload, 'elementId'> | null,
        ): boolean {
            if (!this.allowDragAndDrop || !dragData || !dropData) {
                return false;
            }

            if (dropData.newParentElementId && dragData.subtreeIds.includes(dropData.newParentElementId)) {
                return false;
            }

            if (typeof this.validateMoveTarget === 'function') {
                return this.validateMoveTarget({
                    elementId: dragData.elementId,
                    newParentElementId: dropData.newParentElementId,
                    newSlotName: dropData.newSlotName,
                    newIndex: dropData.newIndex,
                });
            }

            return true;
        },

        onDropElement(dragData: { elementId: string } | null, dropData: Omit<MoveElementPayload, 'elementId'> | null): void {
            if (!dragData || !dropData) {
                return;
            }

            this.$emit('move-element', {
                elementId: dragData.elementId,
                newParentElementId: dropData.newParentElementId,
                newSlotName: dropData.newSlotName,
                newIndex: dropData.newIndex,
            });
        },
    },
});
