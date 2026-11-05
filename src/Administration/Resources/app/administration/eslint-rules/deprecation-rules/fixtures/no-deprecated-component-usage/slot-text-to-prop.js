/**
 * @sw-package framework
 */

// How slot content becomes a prop value, and when the slot is left for a manual move. Uses the
// "label" slot of mt-text-field, which the registry moves into the "label" prop.

const HEADLINE = '[mt-text-field] The "label" slot is removed. Pass its content as the "label" prop.';
const MANUAL_MOVE = 'Move the content of the "label" slot into the "label" prop manually.';

function manualCase(name, code, reason) {
    return {
        name,
        filename: 'test.html.twig',
        code,
        output: null,
        errors: [{ message: `${HEADLINE}\n${reason} ${MANUAL_MOVE}` }],
    };
}

const slotTextToPropInvalidTests = [
    {
        name: 'a translated label in a twig template becomes a bound prop',
        filename: 'test.html.twig',
        code: `<template><mt-text-field><template #label>{{ $t('sw-foo.label') }}</template></mt-text-field></template>`,
        output: `<template><mt-text-field :label="$t('sw-foo.label')"></mt-text-field></template>`,
        errors: [{ message: HEADLINE }],
    },
    {
        name: 'a translated label in a vue file becomes a bound prop',
        filename: 'test.vue',
        code: `<template><mt-text-field><template #label>{{ $t('sw-foo.label') }}</template></mt-text-field></template>`,
        output: `<template><mt-text-field :label="$t('sw-foo.label')"></mt-text-field></template>`,
        errors: [{ message: HEADLINE }],
    },
    {
        name: 'a comment in the slot moves above the element',
        filename: 'test.html.twig',
        code: `<template>\n\t<mt-text-field><template #label><!-- shown in the form -->Name</template></mt-text-field>\n</template>`,
        output: `<template>\n\t<!-- shown in the form -->\n\t<mt-text-field label="Name"></mt-text-field>\n</template>`,
        errors: [{ message: HEADLINE }],
    },
    {
        name: 'text around a mustache becomes a template literal',
        filename: 'test.html.twig',
        code: `<template><mt-text-field><template #label>Name ({{ count }})</template></mt-text-field></template>`,
        output: '<template><mt-text-field :label="`Name (${count})`"></mt-text-field></template>',
        errors: [{ message: HEADLINE }],
    },
    {
        name: 'text inside a single wrapper element is unwrapped',
        filename: 'test.html.twig',
        code: `<template><mt-text-field><template #label><span>Name</span></template></mt-text-field></template>`,
        output: `<template><mt-text-field label="Name"></mt-text-field></template>`,
        errors: [{ message: HEADLINE }],
    },
    {
        name: 'quotes in static text are encoded for the attribute',
        filename: 'test.html.twig',
        code: `<template><mt-text-field><template #label>The "best" name</template></mt-text-field></template>`,
        output: `<template><mt-text-field label="The &quot;best&quot; name"></mt-text-field></template>`,
        errors: [{ message: HEADLINE }],
    },
    {
        name: 'an empty slot is removed',
        filename: 'test.html.twig',
        code: `<template><mt-text-field>\n    <template #label>  </template>\n</mt-text-field></template>`,
        output: `<template><mt-text-field>\n</mt-text-field></template>`,
        errors: [{ message: HEADLINE }],
    },
    manualCase(
        'markup stays in the slot',
        `<template><mt-text-field><template #label>Name <b>required</b></template></mt-text-field></template>`,
        'The slot contains markup or Twig, which a prop cannot hold.',
    ),
    manualCase(
        'twig stays in the slot',
        `<template><mt-text-field><template #label>{% block sw_foo_label %}Name{% endblock %}</template></mt-text-field></template>`,
        'The slot contains markup or Twig, which a prop cannot hold.',
    ),
    manualCase(
        'a conditional slot stays in place',
        `<template><mt-text-field><template v-if="showLabel" #label>Name</template></mt-text-field></template>`,
        'The slot is rendered conditionally or uses slot props.',
    ),
    manualCase(
        'a scoped slot stays in place',
        `<template><mt-text-field><template #label="{ field }">{{ field.name }}</template></mt-text-field></template>`,
        'The slot is rendered conditionally or uses slot props.',
    ),
    manualCase(
        'a label prop next to the slot is not overwritten',
        `<template><mt-text-field label="Prop"><template #label>Slot</template></mt-text-field></template>`,
        'The element already sets "label".',
    ),
    manualCase(
        'an expression with both quote styles stays in the slot',
        `<template><mt-text-field><template #label>{{ "a" + 'b' }}</template></mt-text-field></template>`,
        'The slot content mixes single and double quotes.',
    ),
];

module.exports = {
    valid: [],
    invalid: slotTextToPropInvalidTests,
};
