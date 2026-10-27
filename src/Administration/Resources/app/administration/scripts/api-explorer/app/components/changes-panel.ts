/**
 * @sw-package framework
 */

import { computed, defineComponent, ref, watch, type PropType } from 'vue';
import type { ApiChange } from '../../diff/types';
import { formatCount, matchesQuery } from '../model/format';
import {
    changeImpact,
    KIND_LABELS,
    matchesFilter,
    PUBLIC_SEVERITIES,
    SEVERITIES,
    SEVERITY_META,
    severityRank,
    type ChangeFilter,
    type Severity,
} from '../model/severity';
import { isLongPair } from '../model/text-diff';
import { hrefForChange } from '../router';
import { ReasonText, SeverityPill, TypeDiff } from './common';

const PAGE_SIZE = 200;

export const ChangesPanel = defineComponent({
    name: 'ChangesPanel',
    components: { SeverityPill, ReasonText, TypeDiff },
    props: {
        changes: { type: Array as PropType<ApiChange[]>, required: true },
        query: { type: String, default: '' },
        /** Selected tile or private impact card, null for all. */
        filter: { type: String as PropType<ChangeFilter | null>, default: null },
    },
    emits: ['update:filter'],
    setup(props, { emit }) {
        const showPrivate = ref(true);
        const limit = ref(PAGE_SIZE);

        const tiles = computed(() =>
            SEVERITIES.map((severity) => ({
                severity,
                label: SEVERITY_META[severity].label,
                symbol: SEVERITY_META[severity].symbol,
                count: props.changes.filter((change) => change.severity === severity).length,
                pressed: props.filter === severity,
            })),
        );

        const privateCards = computed(() =>
            PUBLIC_SEVERITIES.map((impact) => {
                const filter: ChangeFilter = `private:${impact}`;

                return {
                    impact,
                    filter,
                    label: SEVERITY_META[impact].label,
                    symbol: SEVERITY_META[impact].symbol,
                    count: props.changes.filter((change) => change.severity === 'private' && changeImpact(change) === impact)
                        .length,
                    pressed: props.filter === filter,
                };
            }),
        );

        // The diff sorts by severity only; private changes are ordered by impact as well, keeping the diff's order otherwise.
        const order = (change: ApiChange): number => {
            const impact = changeImpact(change);

            return (
                severityRank(change.severity) * (PUBLIC_SEVERITIES.length + 1) +
                (impact === null ? PUBLIC_SEVERITIES.length : PUBLIC_SEVERITIES.indexOf(impact))
            );
        };

        const rows = computed(() =>
            props.changes
                .filter(
                    (change) =>
                        matchesFilter(change, props.filter) &&
                        (showPrivate.value || change.severity !== 'private') &&
                        matchesQuery(`${change.owner} ${change.member}`, props.query),
                )
                .sort((a, b) => order(a) - order(b)),
        );

        const visibleRows = computed(() =>
            rows.value.slice(0, limit.value).map((change) => ({
                change,
                kind: KIND_LABELS[change.kind],
                href: hrefForChange(change),
                longPair: change.kind !== 'block' && isLongPair(change.before, change.after),
                memberLabel:
                    change.kind === 'objectMember' && change.member.startsWith(`${change.owner}.`)
                        ? change.member.slice(change.owner.length + 1)
                        : change.member,
            })),
        );

        const caption = computed(() => {
            const count = formatCount(rows.value.length);

            if (props.filter === null) {
                return `${count} ${rows.value.length === 1 ? 'change' : 'changes'}, sorted by severity`;
            }

            const label = props.filter.startsWith('private:')
                ? `Private · ${SEVERITY_META[props.filter.slice('private:'.length) as Severity].label}`
                : SEVERITY_META[props.filter as Severity].label;

            return `${count} × ${label}. Click the tile again to clear the filter.`;
        });

        watch(
            () => [
                props.filter,
                props.query,
                props.changes,
            ],
            () => {
                limit.value = PAGE_SIZE;
            },
        );

        return {
            tiles,
            privateCards,
            rows,
            visibleRows,
            caption,
            showPrivate,
            pick: (filter: ChangeFilter) => {
                if (filter === 'private' || filter.startsWith('private:')) {
                    showPrivate.value = true;
                }

                emit('update:filter', props.filter === filter ? null : filter);
            },
            showMore: () => {
                limit.value += PAGE_SIZE;
            },
        };
    },
    template: `
        <div class="stack">
            <div class="tiles">
                <button
                    v-for="tile in tiles"
                    :key="tile.severity"
                    type="button"
                    class="tile"
                    :data-severity="tile.severity"
                    :aria-pressed="tile.pressed ? 'true' : 'false'"
                    @click="pick(tile.severity)"
                >
                    <span class="tile__label">
                        <span class="tile__symbol" aria-hidden="true">{{ tile.symbol }}</span>{{ tile.label }}
                    </span>
                    <span class="tile__count">{{ tile.count }}</span>
                    <span v-if="tile.severity === 'private'" class="tile__caption">all private changes</span>
                </button>
            </div>

            <section class="private-cards" aria-labelledby="private-cards-title" data-testid="private-cards">
                <div class="stack stack--tight">
                    <h2 id="private-cards-title" class="private-cards__title">Private API</h2>
                    <p class="muted small">Not part of the public contract. These never turn the check red.</p>
                </div>
                <div class="tiles">
                    <button
                        v-for="card in privateCards"
                        :key="card.impact"
                        type="button"
                        class="tile tile--private"
                        :data-severity="card.impact"
                        :aria-pressed="card.pressed ? 'true' : 'false'"
                        :aria-label="'Private API, ' + card.label + ': ' + card.count"
                        @click="pick(card.filter)"
                    >
                        <span class="tile__label">
                            <span class="tile__symbol" aria-hidden="true">{{ card.symbol }}</span>{{ card.label }}
                        </span>
                        <span class="tile__count">{{ card.count }}</span>
                    </button>
                </div>
            </section>

            <section class="card" aria-labelledby="changes-title">
                <div class="card__header">
                    <div class="stack stack--tight">
                        <h2 id="changes-title" class="card__title">Changes</h2>
                        <p class="muted small">{{ caption }}</p>
                    </div>
                    <div class="toolbar">
                        <label class="checkbox"><input v-model="showPrivate" type="checkbox">Show private</label>
                        <button type="button" class="button button--small" @click="$emit('update:filter', null)">Show all</button>
                    </div>
                </div>
                <div class="table-scroll">
                    <table class="table table--changes">
                        <thead>
                            <tr><th scope="col">Status</th><th scope="col">Kind</th><th scope="col">Location</th><th scope="col">What happened</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in visibleRows" :key="row.change.id" data-testid="change-row">
                                <td><SeverityPill :change="row.change" /></td>
                                <td class="muted-strong">{{ row.kind }}</td>
                                <td>
                                    <a :href="row.href" class="mono member-link"><span class="muted">{{ row.change.owner }} › </span>{{ row.memberLabel }}</a>
                                </td>
                                <td>
                                    <ReasonText v-for="(reason, index) in row.change.reasons" :key="index" :text="reason" class="reason" />
                                    <TypeDiff v-if="row.longPair" :before="row.change.before" :after="row.change.after" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="rows.length === 0" class="empty">No changes match this filter.</p>
                    <div v-if="rows.length > visibleRows.length" class="more">
                        <button type="button" class="button button--small" @click="showMore">
                            Show more ({{ rows.length - visibleRows.length }} left)
                        </button>
                    </div>
                </div>
            </section>
        </div>
    `,
});
