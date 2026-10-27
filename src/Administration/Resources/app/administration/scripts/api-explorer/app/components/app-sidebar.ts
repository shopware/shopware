/**
 * @sw-package framework
 */

import { computed, defineComponent, type PropType } from 'vue';
import type { ApiChange } from '../../diff/types';
import type { CompareResponse } from '../api';
import { formatCount } from '../model/format';
import { countNodes } from '../model/object-tree';
import { SEVERITIES, SEVERITY_META, worstSeverity } from '../model/severity';
import type { Route } from '../router';
import { hrefFor } from '../router';
import { SeverityPill } from './common';

const PLANNED = [
    { label: 'Native blocks', tag: 'sw-block' },
    { label: 'Services', tag: 'DI' },
    { label: 'Stores', tag: 'Pinia, Vuex' },
    { label: 'Mixins and composables', tag: '' },
    { label: 'Directives and filters', tag: '' },
    { label: 'Modules and routes', tag: '' },
    { label: 'Extension SDK', tag: 'positions' },
    { label: 'Snippets and CSS', tag: 'idea' },
];

function tone(changes: ApiChange[]): string {
    const worst = worstSeverity(changes);

    if (worst === 'breaking') {
        return 'breaking';
    }

    return worst === 'potentiallyBreaking' ? 'potentiallyBreaking' : 'neutral';
}

export const AppSidebar = defineComponent({
    name: 'AppSidebar',
    components: { SeverityPill },
    props: {
        route: { type: Object as PropType<Route>, required: true },
        result: { type: Object as PropType<CompareResponse | null>, default: null },
    },
    setup(props) {
        const items = computed(() => {
            const result = props.result;
            const changes = result?.diff.changes ?? [];
            const objectChanges = changes.filter((change) => change.area === 'shopwareObject');
            const componentChanges = changes.filter((change) => change.area === 'component');
            const blockChanges = changes.filter((change) => change.kind === 'block');
            const blocks = result?.head.components.reduce((sum, component) => sum + component.blocks.length, 0) ?? null;
            const active = props.route.name;

            const item = (
                id: string,
                label: string,
                href: string,
                count: number | null,
                itemChanges: ApiChange[],
                isActive: boolean,
                nested = false,
            ) => ({
                id,
                label,
                href,
                count: count === null ? '' : formatCount(count),
                delta: result ? itemChanges.length : null,
                tone: tone(itemChanges),
                current: isActive,
                nested,
            });

            return {
                top: [item('overview', 'Overview', hrefFor({ name: 'overview' }), null, changes, active === 'overview')],
                implemented: [
                    item(
                        'shopware',
                        'Shopware object',
                        hrefFor({ name: 'shopware', path: null }),
                        result ? countNodes(result.head.shopwareObject) : null,
                        objectChanges,
                        active === 'shopware',
                    ),
                    item(
                        'components',
                        'Components',
                        hrefFor({ name: 'components' }),
                        result ? result.head.components.length : null,
                        componentChanges,
                        active === 'components' || active === 'component',
                    ),
                    item(
                        'blocks',
                        'Twig blocks',
                        hrefFor({ name: 'blocks' }),
                        blocks,
                        blockChanges,
                        active === 'blocks',
                        true,
                    ),
                ],
            };
        });

        return {
            items,
            planned: PLANNED,
            privateExamples: [
                { severity: 'private' as const, impact: 'breaking' as const, privateVisibility: 'private' as const },
                { severity: 'private' as const, impact: 'added' as const, privateVisibility: 'internal' as const },
                { severity: 'private' as const, impact: 'compatible' as const, privateVisibility: 'experimental' as const },
            ],
            legend: SEVERITIES.map((severity) => ({ severity, ...SEVERITY_META[severity] })),
        };
    },
    template: `
        <nav class="sidebar" aria-label="Categories">
            <div class="sidebar__group">
                <a v-for="item in items.top" :key="item.id" :href="item.href" class="nav-item" :aria-current="item.current ? 'page' : undefined">
                    <span class="nav-item__label">{{ item.label }}</span>
                    <span v-if="item.delta !== null" class="badge" :data-tone="item.tone" :aria-label="item.delta + ' changes'" data-testid="overview-badge">{{ item.delta }}</span>
                </a>
            </div>

            <div class="sidebar__group">
                <span class="sidebar__heading">In the prototype</span>
                <a v-for="item in items.implemented" :key="item.id" :href="item.href" class="nav-item" :class="{ 'nav-item--nested': item.nested }" :aria-current="item.current ? 'page' : undefined">
                    <span class="nav-item__label">{{ item.label }}</span>
                    <span class="nav-item__count">{{ item.count }}</span>
                    <span v-if="item.delta" class="badge" :data-tone="item.tone" :aria-label="item.delta + ' changes'">{{ item.delta }}</span>
                </a>
            </div>

            <div class="sidebar__group">
                <span class="sidebar__heading">Planned</span>
                <div v-for="item in planned" :key="item.label" class="nav-item nav-item--planned" aria-disabled="true">
                    <span class="nav-item__label">{{ item.label }}</span>
                    <span class="nav-item__tag">{{ item.tag }}</span>
                </div>
            </div>

            <div class="legend">
                <span class="sidebar__heading sidebar__heading--flush">Legend</span>
                <span v-for="entry in legend" :key="entry.severity" class="pill" :data-severity="entry.severity">
                    <span class="pill__symbol" aria-hidden="true">{{ entry.symbol }}</span>{{ entry.label }}
                </span>
                <span class="legend__note">Private changes show the rating they would get if the API were public:</span>
                <SeverityPill v-for="(example, index) in privateExamples" :key="index" :change="example" />
                <span class="legend__note">Only breaking changes turn the CI check red. Changes to private API are shown but don't count as breaks.</span>
            </div>
        </nav>
    `,
});
