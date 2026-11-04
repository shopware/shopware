// How the <option> elements of a select become its "options" prop, and when they are left for a
// manual move. Uses mt-select, whose registry entry reads "value" and the text of each option.

const HEADLINE = '[mt-select] The "default" slot is removed. Pass its <option> elements as the "options" prop.';
const MANUAL_MOVE = 'Move the <option> elements into the "options" prop manually.';
const LIST_REVIEW = 'The items come from "tags", which has to be an array.';
const BOUND_REVIEW = 'sw-select-field passed the selected value on as a string, mt-select passes it on as it is: check that the bound value has the type the model expects.';

function convertedCase(name, code, output, reviews = []) {
    return {
        name,
        filename: 'test.html.twig',
        code,
        output,
        errors: [{ message: reviews.length ? `${HEADLINE}\n${reviews.join(' ')}` : HEADLINE }],
    };
}

function manualCase(name, code, reason) {
    return {
        name,
        filename: 'test.html.twig',
        code,
        output: null,
        errors: [{ message: `${HEADLINE}\n${reason} ${MANUAL_MOVE}` }],
    };
}

const slotToItemsPropInvalidTests = [
    convertedCase(
        'static options become an array, an empty value becomes null',
        `<template><mt-select v-model="x"><option value="a">A</option><option value="">{{ $t('none') }}</option></mt-select></template>`,
        `<template><mt-select :options="[{ value: 'a', label: 'A' }, { value: null, label: $t('none') }]" v-model="x" /></template>`,
    ),
    convertedCase(
        'quotes and entities in static text stay readable inside the attribute',
        `<template><mt-select v-model="x"><option value="a">It's "A" &amp; B</option></mt-select></template>`,
        `<template><mt-select :options="[{ value: 'a', label: 'It\\'s &quot;A&quot; &amp; B' }]" v-model="x" /></template>`,
    ),
    convertedCase(
        'options in a default slot template are moved and other slots stay',
        `<template><mt-select v-model="x"><template #default><option value="a">A</option></template><template #hint>H</template></mt-select></template>`,
        `<template><mt-select :options="[{ value: 'a', label: 'A' }]" v-model="x"><template #hint>H</template></mt-select></template>`,
    ),
    convertedCase(
        'slot props the options do not use do not block the move',
        `<template><mt-select v-model="x"><template #default="{ item }"><option value="a">A</option></template></mt-select></template>`,
        `<template><mt-select :options="[{ value: 'a', label: 'A' }]" v-model="x" /></template>`,
    ),
    convertedCase(
        'a bound value is moved with a review comment, because mt-select keeps its type',
        `<template>\n    <mt-select v-model="x"><option :value="0">auto</option></mt-select>\n</template>`,
        `<template>\n    <!-- TODO Codemod: ${HEADLINE} ${BOUND_REVIEW} -->\n    <mt-select :options="[{ value: 0, label: 'auto' }]" v-model="x" />\n</template>`,
        [BOUND_REVIEW],
    ),
    convertedCase(
        'a v-for list of plain keys is passed as it is with the key props',
        `<template>
    <mt-select v-model="x">
        <option v-for="tag in tags" :key="tag.key" :value="tag.key">
            {{ tag.name }}
        </option>
    </mt-select>
</template>`,
        `<template>
    <!-- TODO Codemod: ${HEADLINE} ${LIST_REVIEW} ${BOUND_REVIEW} -->
    <mt-select :options="tags" value-property="key" label-property="name" v-model="x" />
</template>`,
        [LIST_REVIEW, BOUND_REVIEW],
    ),
    convertedCase(
        'a v-for list with computed labels is mapped, next to a static option',
        `<template>
    <mt-select
        v-model="x"
        :label="$t('a')"
    >
        <option value="">{{ $t('none') }}</option>
        <option v-for="(tag, index) in tags" :value="tag">{{ $t(tag) }} {{ index }}</option>
    </mt-select>
</template>`,
        `<template>
    <!-- TODO Codemod: ${HEADLINE} ${LIST_REVIEW} ${BOUND_REVIEW} -->
    <mt-select
        :options="[
            { value: null, label: $t('none') },
            ...tags.map((tag, index) => ({ value: tag, label: \`\${$t(tag)} \${index}\` })),
        ]"
        v-model="x"
        :label="$t('a')"
    />
</template>`,
        [LIST_REVIEW, BOUND_REVIEW],
    ),
    manualCase(
        'an option with another attribute stays',
        `<template><mt-select v-model="x"><option value="slide" selected>Slide</option></mt-select></template>`,
        'A <option> element has "selected", which an item of "options" cannot hold.',
    ),
    manualCase(
        'an option without value stays, because the select used its text as value',
        `<template><mt-select v-model="x"><option>Alpha</option></mt-select></template>`,
        'A <option> element has no "value", which every item needs.',
    ),
    manualCase(
        'an option that iterates over a number stays',
        `<template><mt-select v-model="x"><option v-for="i in 48" :value="i">{{ i }}</option></mt-select></template>`,
        'A <option> element iterates over a number.',
    ),
    manualCase(
        'markup in an option stays',
        `<template><mt-select v-model="x"><option value="a">A <b>!</b></option></mt-select></template>`,
        'A <option> element contains markup, which an item of "options" cannot hold.',
    ),
    manualCase(
        'twig between the options stays',
        `<template><mt-select v-model="x"><option value="a">A</option>{% if b %}<option value="b">B</option>{% endif %}</mt-select></template>`,
        'The items contain Twig, which a prop cannot hold.',
    ),
    manualCase(
        'options that use slot props stay',
        `<template><mt-select v-model="x"><template #default="{ item }"><option :value="item">A</option></template></mt-select></template>`,
        'The slot is rendered conditionally or its items use slot props.',
    ),
    manualCase(
        'other content next to the options stays',
        `<template><mt-select v-model="x"><option value="a">A</option><span>x</span></mt-select></template>`,
        'The slot holds other content than <option> elements.',
    ),
    manualCase(
        'option groups stay, even without a single option element next to them',
        `<template><mt-select v-model="x"><optgroup v-for="group in groups" :label="group.label"><option value="a">A</option></optgroup></mt-select></template>`,
        'The slot holds other content than <option> elements.',
    ),
    manualCase(
        'options next to an options prop stay',
        `<template><mt-select :options="list"><option value="a">A</option></mt-select></template>`,
        'The element already sets "options".',
    ),
];

module.exports = {
    valid: [
        {
            name: 'a select without options in its slot is not reported',
            filename: 'test.html.twig',
            code: `<template><mt-select v-model="x" :options="list"><template #hint>H</template></mt-select></template>`,
        },
    ],
    invalid: slotToItemsPropInvalidTests,
};
