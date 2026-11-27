<template>
    <main class="overview">
        <header class="overview__head">
            <mt-text
                as="h1"
                size="3xl"
                weight="semibold"
            >
                Compare page: four directions
            </mt-text>
            <mt-text
                size="m"
                class="overview__lead"
            >
                Four layouts for the page that runs the public API break check in the browser. They are built with the
                Meteor components and show real findings: pull request #20959 with 26 findings, and v6.7.0.0 against
                trunk with 3,872. A dark bar at the bottom of each page switches between the states. It is a review
                tool and not part of any design.
            </mt-text>
        </header>

        <section class="overview__directions">
            <mt-card
                v-for="direction in DIRECTIONS"
                :key="direction.id"
                :title="direction.name"
                :subtitle="direction.idea"
            >
                <div class="overview__card">
                    <p class="overview__states">
                        <mt-link
                            as="a"
                            :to="`${direction.id}.html#small`"
                        >
                            Open
                        </mt-link>
                        <span class="overview__muted">or go to</span>
                        <template
                            v-for="(state, index) in STATES"
                            :key="state.name"
                        >
                            <span
                                v-if="index > 0"
                                class="overview__muted"
                            >·</span>
                            <mt-link
                                as="a"
                                :to="`${direction.id}.html#${state.name}`"
                            >
                                {{ state.label.toLowerCase() }}
                            </mt-link>
                        </template>
                    </p>
                    <div class="overview__columns">
                        <div>
                            <mt-text
                                as="h3"
                                size="xs"
                                weight="semibold"
                            >
                                Serves best
                            </mt-text>
                            <p>{{ NOTES[direction.id].serves }}</p>
                        </div>
                        <div>
                            <mt-text
                                as="h3"
                                size="xs"
                                weight="semibold"
                            >
                                Leaves out, compared with the prototype
                            </mt-text>
                            <ul>
                                <li
                                    v-for="item in NOTES[direction.id].leavesOut"
                                    :key="item"
                                >
                                    {{ item }}
                                </li>
                            </ul>
                        </div>
                        <div>
                            <mt-text
                                as="h3"
                                size="xs"
                                weight="semibold"
                            >
                                Weak where
                            </mt-text>
                            <ul>
                                <li
                                    v-for="item in NOTES[direction.id].weak"
                                    :key="item"
                                >
                                    {{ item }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </mt-card>
        </section>

        <section class="overview__section">
            <mt-text
                as="h2"
                size="xl"
                weight="semibold"
            >
                Side by side
            </mt-text>
            <div class="overview__frame">
                <table class="overview__table">
                    <thead>
                        <tr>
                            <th />
                            <th>Prototype today</th>
                            <th
                                v-for="direction in DIRECTIONS"
                                :key="direction.id"
                            >
                                {{ direction.name }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in COMPARISON"
                            :key="row.aspect"
                        >
                            <th>{{ row.aspect }}</th>
                            <td
                                v-for="(cell, index) in row.cells"
                                :key="index"
                            >
                                {{ cell }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="overview__section">
            <mt-text
                as="h2"
                size="xl"
                weight="semibold"
            >
                Dropped by all four
            </mt-text>
            <ul class="overview__list">
                <li>
                    <strong>The summary as the command prints it.</strong> The job summary of the pull request check
                    already shows that text, and the page renders the same findings in a form that reads better.
                </li>
                <li>
                    <strong>The token field on the start screen.</strong> A comparison takes two to four of the 60
                    requests GitHub allows per hour, so most people never need it. It appears in the request limit
                    error, where it solves the problem in front of the reader.
                </li>
                <li>
                    <strong>The flag states on every finding.</strong> Almost all findings hold with the major flags on
                    and off. A finding names its flag state only when it holds in one of them.
                </li>
                <li>
                    <strong>Most of the footer.</strong> The sentence about the merge base goes. All four still name
                    the generator commit next to the compared commits, because the page computes with the generator of
                    trunk while the check in the pipeline uses its own. To-do also keeps the sentence that the check
                    decides.
                </li>
            </ul>
        </section>

        <section class="overview__section">
            <mt-text
                as="h2"
                size="xl"
                weight="semibold"
            >
                Worth combining
            </mt-text>
            <ul class="overview__list">
                <li>
                    The fix grouping of To-do with the search of One field: the to-do list for a pull request and the
                    search for a release comparison, on one page.
                </li>
                <li>
                    The component index of Components as the result of a search in One field, for plugin developers
                    who look up the components they override.
                </li>
                <li>
                    The single field of One field also fits into To-do and Components: it removes the choice between a
                    pull request and two revisions without hiding either.
                </li>
            </ul>
        </section>

        <section class="overview__section">
            <mt-text
                as="h2"
                size="xl"
                weight="semibold"
            >
                What these pages are not
            </mt-text>
            <ul class="overview__list">
                <li>
                    They do not call GitHub or run the check. The findings are those of the command for the same two
                    commits, and the loading state plays a progress with the real file count of 2,719.
                </li>
                <li>
                    Entering #20959 in a start screen leads to the small report, any other number to the unknown pull
                    request error, and two revisions to the large report.
                </li>
                <li>
                    Light theme only. The Meteor component styles bring the light tokens. Dark needs the separate token
                    file of <code>meteor-tokens</code> and a theme switch, so it is left out.
                </li>
            </ul>
        </section>
    </main>
</template>

<script setup lang="ts">/* eslint-disable sw-core-rules/valid-shopware-setup -- a page of the design ideas, not a component of the Administration */
 
/**
 * @sw-package framework
 *
 * The overview: links every direction and compares them.
 */
import { MtCard, MtLink, MtText } from '@shopware-ag/meteor-component-library';
import { DIRECTIONS } from './directions';
import { STATES } from './shared/states';

const NOTES: Record<string, { serves: string; leavesOut: string[]; weak: string[] }> = {
    'to-do': {
        serves: 'The pull request author whose check is red: the page answers "what do I change" with one item per fix and the marker to copy. Reviewers find additions and allowed changes folded below.',
        leavesOut: [
            'Search and the group switches. A pull request has one break in the median and at most 8 in 90 % of the cases, so the list is short and the order replaces the switches: breaks open, the rest folded.',
            'Base and head fields until asked for. Most people arrive from the pull request check or type a pull request number.',
            'Private findings. Only their number is left, because they are never public API.',
            'One explanation per place. A fix item shows the explanation of its first place, because the others differ only in the element name.',
        ],
        weak: [
            'The plugin developer in the large report: 148 fix items and no search. Ctrl+F does not find places folded behind "Show all".',
            'The reviewer has to open a fold to see new public API.',
            '"Why, for the first place" can mislead when the places of one fix differ in more than the name.',
        ],
    },
    components: {
        serves: 'The plugin developer comparing two releases: filter the index by the components the extension overrides and read everything that changed in each.',
        leavesOut: [
            'Full-text search. The filter matches the names of components, services and stores only.',
            'The verdict as a sentence. Counts per component stand in for it.',
            'Grouping by fix. The same fix appears on each finding it applies to.',
            'The intro text. The bar is the whole start screen besides an empty state.',
        ],
        weak: [
            'A small pull request: the author sees one component at a time and clicks through. In #20959, 3 of the 5 components have breaks.',
            'No place shows all breaks at once, which is what a red check asks for.',
            'A block name typed into the filter finds nothing, because only unit names are searched.',
            'Two panes need about 1,100 pixels of width.',
        ],
    },
    listing: {
        serves: 'Anyone who works in the Administration every day: it behaves like every other list there, and search, filter and sorting cover all three readers in some way.',
        leavesOut: [
            'Grouping of any kind. Every finding is one row.',
            'The fix and the explanation in the list. They open in a modal on click.',
            'Private findings at first. They are one filter option away.',
            'The intro text and the summary text.',
        ],
        weak: [
            'The least minimal direction: a card, a banner, a table toolbar, filter chips and pages before and around the findings.',
            'The fix needs a click and a modal per finding, and one fix shared by 78 blocks is 78 rows.',
            'Long element names are cramped in table columns.',
        ],
    },
    'one-field': {
        serves: 'Repeat users who know what they look for, and the reviewer: the counts above the list are shortcuts that narrow it to breaks, additions or info.',
        leavesOut: [
            'Separate fields for pull request, base and head. One field reads 20959, #20959, a pull request link or base...head.',
            'Group switches. The counts fill the search instead.',
            'Cards. Each finding is one line that opens to its explanation, fix and source.',
            'Private findings, apart from their number.',
            'Rendering the whole large report. It shows 100 findings and more on request.',
        ],
        weak: [
            'The base...head syntax has to be learned. The start screen shows two examples, but a field that takes everything says less about what it takes.',
            'An author opens each break on its own to read the fix, and fixes shared by several places are not grouped.',
            'A red check does not say "fix these" as clearly as To-do does.',
        ],
    },
};

const COMPARISON: ReadonlyArray<{ aspect: string; cells: string[] }> = [
    {
        aspect: 'Comparison input',
        cells: [
            'Pull request, or base and head, in one form with an optional token',
            'Pull request field. Base and head behind a link. Shrinks to one line with "Change" once a report is open',
            'A bar with all three fields, always visible',
            'A card with all three fields above the list',
            'One field that reads every form',
        ],
    },
    {
        aspect: 'Findings ordered by',
        cells: ['Group, as a flat list of cards', 'Distinct fix', 'Component, service or store', 'Table rows, sortable', 'Group, one line each'],
    },
    {
        aspect: 'Large report stays usable by',
        cells: [
            'Group switches and a text filter, all matches rendered',
            'Fix items show 5 places until "Show all"',
            'Index with a name filter, one unit at a time',
            'Search, group filter and pages of 25',
            'Search and 100 lines at a time',
        ],
    },
    {
        aspect: 'Search',
        cells: ['Full text', 'None', 'Unit names', 'Full text', 'Full text'],
    },
    {
        aspect: 'Fix visible without a click',
        cells: ['Yes', 'Yes, with a copy button for the marker', 'Yes', 'No, in a modal', 'No, the line opens'],
    },
    {
        aspect: 'Private findings',
        cells: ['Switch, off at first', 'Count only', 'Switch, off at first', 'Filter option, off at first', 'Count only'],
    },
    {
        aspect: 'Token field',
        cells: ['Folded on the start screen', 'In the limit error', 'In the limit error', 'In the limit error', 'In the limit error'],
    },
    {
        aspect: 'Loading',
        cells: [
            'A status line and a progress bar',
            'Progress bar with the file count',
            'Progress bar and a skeleton of the index',
            'Progress bar in the card, skeleton rows in the table',
            'Progress bar under the field',
        ],
    },
];
</script>

<style>
.overview {
    max-width: 1200px;
    margin: 0 auto;
    padding: 48px 24px 80px;
    display: flex;
    flex-direction: column;
    gap: 40px;
}

.overview__head {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.overview__lead {
    max-width: 75ch;
}

.overview__directions {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.overview__directions .mt-card {
    margin: 0;
    max-width: none;
}

.overview__card {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.overview__states {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 6px;
}

.overview__muted {
    color: var(--color-text-secondary-default);
}

.overview__columns {
    display: grid;
    grid-template-columns: 1fr 1.4fr 1.4fr;
    gap: 24px;
}

.overview__columns ul,
.overview__list {
    margin: 6px 0 0;
    padding-left: 18px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.overview__columns p {
    margin-top: 6px;
}

.overview__section {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.overview__list {
    max-width: 80ch;
}

.overview__frame {
    overflow-x: auto;
    background: var(--color-background-primary-default);
    border: 1px solid var(--color-border-primary-default);
    border-radius: var(--border-radius-card);
}

.overview__table {
    width: 100%;
    border-collapse: collapse;
    font-size: var(--font-size-xs);
}

.overview__table th,
.overview__table td {
    padding: 10px 12px;
    text-align: left;
    vertical-align: top;
    border-bottom: 1px solid var(--color-border-primary-default);
}

.overview__table thead th {
    background: var(--color-background-tertiary-default);
    white-space: nowrap;
}

.overview__table tbody th {
    width: 150px;
}

.overview__table tbody tr:last-child > * {
    border-bottom: 0;
}

@media (max-width: 900px) {
    .overview__columns {
        grid-template-columns: 1fr;
    }
}
</style>
