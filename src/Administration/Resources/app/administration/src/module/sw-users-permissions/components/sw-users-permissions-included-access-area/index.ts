/**
 * @sw-package fundamentals@framework
 */
import { MtCollapsible, MtCollapsibleContent, MtCollapsibleTrigger } from '@shopware-ag/meteor-component-library';
import type { IncludedAccessArea, IncludedAccessColumn, Operation } from '../sw-users-permissions-included-access-modal';
import template from './sw-users-permissions-included-access-area.html.twig';
import './sw-users-permissions-included-access-area.scss';

/**
 * One area of the included access matrix: a row with its operations, which expands to the data of the area.
 * Renders a `tbody`, so it belongs into the matrix table.
 *
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    components: {
        MtCollapsible,
        MtCollapsibleTrigger,
        MtCollapsibleContent,
    },

    props: {
        area: {
            type: Object as PropType<IncludedAccessArea>,
            required: true,
        },

        columns: {
            type: Array as PropType<IncludedAccessColumn[]>,
            required: true,
        },

        showGrantedBy: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    methods: {
        columnLabel(column: IncludedAccessColumn): string {
            return this.$t(`sw-privileges.roles.${column.role}`);
        },

        /**
         * The tooltip renders HTML, so the labels are escaped before they are joined with line breaks.
         */
        escapeHtml(text: string): string {
            const element = document.createElement('span');
            element.textContent = text;

            return element.innerHTML;
        },

        sourcesTooltip(sources: string[]): string {
            return sources.map((source) => this.escapeHtml(source)).join(',<br>');
        },

        markTooltip(operation: Operation): string {
            const { level, entities, sources } = this.area.coverage[operation];

            if (level !== 'some') {
                return this.sourcesTooltip(sources);
            }

            const summary = this.$t('sw-users-permissions.roles.included-access.partialAccess', {
                count: entities.length,
                total: this.area.entityCount,
                entities: entities.join(', '),
            });

            return `${this.escapeHtml(summary)}<br>${this.sourcesTooltip(sources)}`;
        },

        markLabel(operation: Operation): string {
            if (this.area.coverage[operation].level !== 'some') {
                return this.$t('sw-users-permissions.roles.included-access.fullAccess');
            }

            return this.$t('sw-users-permissions.roles.included-access.partialAccessShort', {
                count: this.area.coverage[operation].entities.length,
                total: this.area.entityCount,
            });
        },
    },
});
