<template>
    <main class="todo">
        <template v-if="state.kind === 'start' || editing">
            <div
                v-if="state.kind === 'start'"
                class="todo__intro"
            >
                <mt-text
                    as="h1"
                    size="2xl"
                    weight="semibold"
                >
                    Check the public API of a change
                </mt-text>
                <mt-text color="color-text-secondary-default">
                    Lists what a change breaks for extensions and what it adds. It runs in your browser on the
                    sources GitHub serves.
                </mt-text>
            </div>
            <form
                class="todo__form"
                @submit.prevent="submit"
            >
                <div class="todo__fields">
                    <mt-text-field
                        v-if="mode === 'pr'"
                        v-model="pullRequest"
                        class="todo__pr"
                        label="Pull request"
                        placeholder="20959"
                    />
                    <template v-else>
                        <mt-text-field
                            v-model="base"
                            label="Base"
                            placeholder="v6.7.0.0"
                        />
                        <mt-text-field
                            v-model="head"
                            label="Head"
                            placeholder="trunk"
                        />
                    </template>
                    <mt-button
                        type="submit"
                        variant="primary"
                        size="large"
                    >
                        Compare
                    </mt-button>
                </div>
                <mt-link
                    as="button"
                    @click.prevent="mode = mode === 'pr' ? 'revisions' : 'pr'"
                >
                    {{ mode === 'pr' ? 'Compare two branches, tags or commits instead' : 'Compare a pull request instead' }}
                </mt-link>
            </form>
        </template>

        <p
            v-if="state.kind !== 'start' && !editing"
            class="todo__comparison"
        >
            <mt-link
                v-if="pullRequestNumber !== null"
                as="a"
                type="external"
                target="_blank"
                :to="pullRequestUrl(pullRequestNumber)"
            >
                Pull request #{{ pullRequestNumber }}
            </mt-link>
            <span>{{ comparisonText }}</span>
            <mt-link
                v-if="state.kind !== 'loading'"
                as="button"
                @click="editing = true"
            >
                Change
            </mt-link>
        </p>

        <section
            v-if="state.kind === 'loading'"
            class="todo__loading"
        >
            <mt-progress-bar
                :label="progress.message"
                :model-value="progress.loaded"
                :max-value="progress.total"
            />
            <mt-text
                size="xs"
                color="color-text-secondary-default"
            >
                The first comparison takes about 15 seconds. Later ones reuse the files they already loaded.
            </mt-text>
        </section>

        <section
            v-if="state.kind === 'error'"
            class="todo__error"
        >
            <mt-banner
                :variant="state.reason === 'limit' ? 'attention' : 'critical'"
                :title="state.reason === 'limit' ? 'Request limit reached' : 'Pull request not found'"
            >
                {{ state.message }}
                <template v-if="state.reason === 'unknown'">
                    Check the number, or compare two revisions instead.
                </template>
            </mt-banner>
            <form
                v-if="state.reason === 'limit'"
                class="todo__fields"
                @submit.prevent="emit('compare', state.request)"
            >
                <mt-password-field
                    v-model="token"
                    label="GitHub token"
                    help-text="A token lifts the limit. It stays in this tab and is only sent to api.github.com."
                />
                <mt-button
                    type="submit"
                    variant="primary"
                    size="large"
                >
                    Try again
                </mt-button>
            </form>
            <mt-button
                v-else
                variant="secondary"
                @click="editing = true"
            >
                Change the comparison
            </mt-button>
        </section>

        <template v-if="report !== null">
            <header class="todo__verdict">
                <mt-text
                    as="h1"
                    size="2xl"
                    weight="semibold"
                    :color="breaks.length > 0 ? 'color-text-critical-default' : 'color-text-positive-default'"
                >
                    {{ breaks.length > 0 ? `${plural(breaks.length, 'break')} to fix` : 'No breaks' }}
                </mt-text>
                <mt-text
                    v-if="breaks.length > 0"
                    color="color-text-secondary-default"
                >
                    {{ plural(fixes.length, 'distinct fix', 'distinct fixes') }}, the largest first.
                </mt-text>
            </header>

            <ol class="todo__fixes">
                <li
                    v-for="item in fixes"
                    :key="item.key"
                    class="todo__fix"
                >
                    <div class="todo__fix-head">
                        <mt-text
                            as="h2"
                            size="m"
                            weight="semibold"
                        >
                            {{ item.title }}
                        </mt-text>
                        <mt-badge
                            size="s"
                            variant="neutral"
                        >
                            {{ plural(item.places.length, 'place') }}
                        </mt-badge>
                        <span
                            v-if="item.stateNote"
                            class="todo__muted"
                        >{{ item.stateNote }}</span>
                    </div>
                    <p
                        v-if="item.fix"
                        class="todo__fix-text"
                    >
                        <fix-text :fix="item.fix" />
                    </p>
                    <mt-text
                        size="xs"
                        color="color-text-secondary-default"
                        class="todo__why"
                    >
                        {{ item.places.length > 1 ? 'Why, for the first place:' : 'Why:' }}
                        {{ item.places[0].explanation }}
                    </mt-text>
                    <div
                        v-for="unit in shownUnits(item)"
                        :key="unit.name"
                        class="todo__unit"
                    >
                        <code class="todo__unit-name">{{ unit.name }}</code>
                        <ul class="todo__places">
                            <li
                                v-for="place in unit.places"
                                :key="place.element"
                            >
                                <code class="todo__place-element">{{ memberText(place) }}</code>
                                <span
                                    v-if="placeNote(item, place)"
                                    class="todo__muted"
                                >{{ placeNote(item, place) }}</span>
                                <span
                                    v-if="place.at"
                                    class="at-end"
                                >
                                    <mt-link
                                        as="a"
                                        type="external"
                                        target="_blank"
                                        :to="sourceUrl(report.head, place.at)"
                                    >
                                        {{ shortAt(place.at) }}
                                    </mt-link>
                                </span>
                            </li>
                        </ul>
                    </div>
                    <mt-button
                        v-if="item.places.length > PLACES_SHOWN"
                        size="x-small"
                        variant="secondary"
                        ghost
                        @click="toggle(item.key)"
                    >
                        {{ expanded.has(item.key) ? 'Show fewer' : `Show all ${item.places.length}` }}
                    </mt-button>
                </li>
            </ol>

            <section
                v-if="reviewSections.length > 0"
                class="todo__review"
            >
                <mt-text
                    as="h2"
                    size="l"
                    weight="semibold"
                >
                    For review
                </mt-text>
                <mt-collapsible
                    v-for="section in reviewSections"
                    :key="section.group"
                    class="todo__disclosure"
                >
                    <mt-collapsible-trigger class="todo__disclosure-trigger">
                        <mt-icon
                            name="regular-chevron-right-xs"
                            size="10px"
                            class="todo__chevron"
                        />
                        <strong>{{ plural(section.size, section.word, section.words) }}</strong>
                        <span class="todo__muted">{{ section.meaning }}</span>
                    </mt-collapsible-trigger>
                    <mt-collapsible-content>
                        <dl class="todo__units">
                            <template
                                v-for="unit in section.units"
                                :key="unit.id"
                            >
                                <dt><code>{{ unit.name }}</code></dt>
                                <dd>
                                    <template
                                        v-for="finding in unit.findings"
                                        :key="finding.element"
                                    >
                                        <mt-link
                                            v-if="finding.at"
                                            as="a"
                                            target="_blank"
                                            :to="sourceUrl(report.head, finding.at)"
                                        >
                                            {{ memberText(finding) }}
                                        </mt-link>
                                        <span v-else>{{ memberText(finding) }}</span>
                                    </template>
                                </dd>
                            </template>
                        </dl>
                    </mt-collapsible-content>
                </mt-collapsible>
            </section>

            <mt-text
                v-if="privateCount > 0"
                size="xs"
                color="color-text-secondary-default"
            >
                {{ plural(privateCount, 'change') }} to private elements are left out. They are never public API.
            </mt-text>

            <footer class="todo__footer">
                Computed in your browser with the generator of <code>{{ GENERATOR }}</code>. The check on the pull
                request decides whether it may merge.
            </footer>
        </template>
    </main>
</template>

<script setup lang="ts">/* eslint-disable sw-core-rules/valid-shopware-setup -- a page of the design ideas, not a component of the Administration */
 
/**
 * @sw-package framework
 *
 * Direction "To-do": the breaks as a list of distinct fixes, everything else folded below.
 */
import {
    MtBadge,
    MtBanner,
    MtButton,
    MtCollapsible,
    MtCollapsibleContent,
    MtCollapsibleTrigger,
    MtIcon,
    MtLink,
    MtPasswordField,
    MtProgressBar,
    MtText,
    MtTextField,
} from '@shopware-ag/meteor-component-library';
import { computed, ref } from 'vue';
import FixText from '../shared/fix-text.vue';
import { fixTextOf, groupBy, memberText, plural, pullRequestUrl, shortAt, sourceUrl, stateNote, unitOf } from '../shared/report';
import type { Finding, FindingGroup } from '../shared/report';
import { GENERATOR } from '../shared/states';
import type { CompareRequest, Progress, ViewState } from '../shared/states';

const props = defineProps<{ state: ViewState; progress: Progress }>();
const emit = defineEmits<{ compare: [request: CompareRequest] }>();

const PLACES_SHOWN = 5;

const editing = ref(false);
const mode = ref<'pr' | 'revisions'>(
    props.state.kind !== 'start' && !('pullRequest' in props.state.request) ? 'revisions' : 'pr',
);
const request = props.state.kind === 'start' ? null : props.state.request;
const pullRequest = ref(request !== null && 'pullRequest' in request ? String(request.pullRequest) : '');
const base = ref(request !== null && 'base' in request ? request.base : '');
const head = ref(request !== null && 'head' in request ? request.head : '');
const token = ref('');

function submit(): void {
    if (mode.value === 'pr' && pullRequest.value.trim() !== '') {
        emit('compare', { pullRequest: Number(pullRequest.value.replace(/\D/g, '')) });
    } else if (mode.value === 'revisions' && base.value.trim() !== '' && head.value.trim() !== '') {
        emit('compare', { base: base.value.trim(), head: head.value.trim() });
    }
}

const report = computed(() => (props.state.kind === 'report' ? props.state.report : null));
const findings = computed(() => report.value?.findings ?? []);

const comparisonText = computed(() => {
    if (props.state.kind === 'start') {
        return '';
    }

    const { request: compared } = props.state;
    const what = 'pullRequest' in compared ? '' : `${compared.base} → ${compared.head}`;
    const commits = report.value === null ? '' : `${report.value.base.slice(0, 12)} → ${report.value.head.slice(0, 12)}`;

    return [what, commits].filter(Boolean).join(' · ');
});

const pullRequestNumber = computed(() =>
    props.state.kind !== 'start' && 'pullRequest' in props.state.request ? props.state.request.pullRequest : null,
);

const breaks = computed(() => findings.value.filter(({ group }) => group === 'Break'));

interface FixItem {
    key: string;
    title: string;
    fix: string | undefined;
    places: Finding[];
    /** Set when every place holds in only one flag state, so the places need not repeat it. */
    stateNote: string | null;
}

const fixes = computed<FixItem[]>(() =>
    [...groupBy(breaks.value, (finding) => finding.fix ?? finding.rule).entries()]
        .map(([key, places]) => {
            const { fix } = places[0];
            const pattern = fix === undefined ? null : fixTextOf(fix).pattern;

            const notes = new Set(places.map(stateNote));

            return {
                key,
                fix,
                places,
                title: pattern ?? places[0].rule.replaceAll('_', ' '),
                stateNote: notes.size === 1 ? stateNote(places[0]) : null,
            };
        })
        .sort((one, other) => other.places.length - one.places.length),
);

const expanded = ref(new Set<string>());

function toggle(key: string): void {
    const next = new Set(expanded.value);

    if (!next.delete(key)) {
        next.add(key);
    }

    expanded.value = next;
}

function shownUnits(item: FixItem): Array<{ name: string; places: Finding[] }> {
    const places = expanded.value.has(item.key) ? item.places : item.places.slice(0, PLACES_SHOWN);

    return [...groupBy(places, (place) => unitOf(place).name).entries()].map(([name, unitPlaces]) => ({
        name,
        places: unitPlaces,
    }));
}

function placeNote(item: FixItem, place: Finding): string {
    return [
        item.stateNote === null ? stateNote(place) : null,
        place.possibleRename === undefined ? null : `possibly renamed to ${place.possibleRename}`,
        place.alsoAffects === undefined ? null : `also affects ${place.alsoAffects.join(', ')}`,
    ]
        .filter((note) => note !== null)
        .join(' · ');
}

const REVIEW: ReadonlyArray<{ group: FindingGroup; word: string; words: string; meaning: string }> = [
    { group: 'Addition', word: 'addition', words: 'additions', meaning: 'become public API with this change' },
    { group: 'Allowed', word: 'allowed change', words: 'allowed changes', meaning: 'were announced before' },
    { group: 'Info', word: 'note', words: 'notes', meaning: 'need no change' },
];

const reviewSections = computed(() =>
    REVIEW.map((section) => {
        const members = findings.value.filter(({ group }) => group === section.group);
        const units = [...groupBy(members, (finding) => unitOf(finding).id).entries()].map(([id, unitFindings]) => ({
            id,
            name: unitOf(unitFindings[0]).name,
            findings: unitFindings,
        }));

        return { ...section, size: members.length, units };
    }).filter(({ size }) => size > 0),
);

const privateCount = computed(() => findings.value.filter(({ group }) => group === 'Private').length);
</script>

<style>
.todo {
    max-width: 780px;
    margin: 0 auto;
    padding: 48px 16px;
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.todo__intro {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.todo__form {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
}

.todo__fields {
    display: flex;
    align-items: flex-end;
    gap: 12px;
    flex-wrap: wrap;
}

.todo__pr {
    width: 200px;
}


.todo__comparison {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--color-text-secondary-default);
}

.todo__loading,
.todo__error {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
}

.todo__loading .mt-progress-bar {
    width: 100%;
}

.todo__verdict {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.todo__fixes {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.todo__fix {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
    padding: 20px 24px;
    background: var(--color-elevation-surface-raised, #fff);
    border: 1px solid var(--color-border-primary-default);
    border-radius: var(--border-radius-card);
}

.todo__fix-head {
    display: flex;
    align-items: center;
    gap: 10px;
}

.todo__fix-head h2::first-letter {
    text-transform: uppercase;
}

.todo__fix-text {
    max-width: 68ch;
}

.todo__why {
    max-width: 68ch;
}

.todo__unit {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.todo__unit-name {
    font-weight: 600;
}

.todo__places {
    list-style: none;
    margin: 0;
    padding: 0 0 0 12px;
    border-left: 2px solid var(--color-border-critical-default);
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.todo__places > li {
    display: flex;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 2px 12px;
}

.todo__place-element {
    overflow-wrap: anywhere;
}


.todo__review {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.todo__disclosure {
    border-top: 1px solid var(--color-border-primary-default);
    padding-top: 8px;
}

.todo__disclosure-trigger {
    all: unset;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 0;
}

.todo__disclosure-trigger:focus-visible {
    outline: 2px solid var(--color-border-brand-selected);
}

.todo__disclosure-trigger[data-state="open"] .todo__chevron {
    transform: rotate(90deg);
}

.todo__muted {
    color: var(--color-text-secondary-default);
}

.todo__units {
    display: grid;
    grid-template-columns: minmax(160px, max-content) 1fr;
    gap: 6px 16px;
    margin: 8px 0 8px 18px;
}

.todo__units dd {
    margin: 0;
    display: flex;
    flex-wrap: wrap;
    gap: 4px 12px;
}

.todo__units dt {
    overflow-wrap: anywhere;
}

.todo__footer {
    font-size: var(--font-size-xs);
    color: var(--color-text-secondary-default);
    border-top: 1px solid var(--color-border-primary-default);
    padding-top: 12px;
}
</style>
