const { rule, tester } = require('./tester');

const CONVERTED = '<!-- TODO Codemod: Converted from sw-text-field - please check if everything works correctly -->';
const NOT_CONVERTED = /^"sw-text-field" is deprecated\. Please use "mt-text-field" instead\.\nIt is not converted, because these parts need a manual migration first:\n- \[sw-text-field\] The "label" slot is removed\. Pass its content as the "label" prop\.\nThe slot contains markup or Twig/;

tester.run('no-deprecated-components (partial conversion)', rule, {
    valid: [],
    invalid: [
        {
            name: 'converts an element whose follow-up steps all fix automatically',
            filename: 'test.html.twig',
            options: [{ partial: false }],
            code: '<template>\n<sw-text-field label="Name" />\n</template>',
            output: `<template>\n${CONVERTED}\n<mt-text-field label="Name" />\n</template>`,
            errors: [{ message: /^"sw-text-field" is deprecated\. Please use "mt-text-field" instead\./ }],
        },
        {
            name: 'keeps an element with a part that needs a manual migration and names that part',
            filename: 'test.html.twig',
            options: [{ partial: false }],
            code: '<template>\n<sw-text-field><template #label>Name <b>required</b></template></sw-text-field>\n</template>',
            output: null,
            errors: [{ message: NOT_CONVERTED }],
        },
        {
            name: 'keeps an element whose follow-up step would only leave a review comment',
            filename: 'test.html.twig',
            options: [{ partial: false }],
            code: '<template>\n<sw-alert><template #actions><mt-button>Retry</mt-button></template></sw-alert>\n</template>',
            output: null,
            errors: [{ message: /It is not converted[\s\S]*- \[sw-alert\] The "actions" slot is removed\./ }],
        },
        {
            name: 'converts the same element by default and leaves the manual part to the follow-up rule',
            filename: 'test.html.twig',
            code: '<template>\n<sw-text-field><template #label>Name <b>required</b></template></sw-text-field>\n</template>',
            output: `<template>\n${CONVERTED}\n<mt-text-field><template #label>Name <b>required</b></template></mt-text-field>\n</template>`,
            errors: [{ message: /^"sw-text-field" is deprecated\. Please use "mt-text-field" instead\./ }],
        },
    ],
});


tester.run('no-deprecated-components (partial conversion of a manual rename)', rule, {
    valid: [],
    invalid: [
        {
            name: 'reports a component with a manual rename without listing its follow-up steps',
            filename: 'test.html.twig',
            options: [{ partial: false }],
            code: '<template>\n<sw-tabs><sw-tabs-item name="a">A</sw-tabs-item></sw-tabs>\n</template>',
            output: null,
            errors: [{ message: /^"sw-tabs" is deprecated\. Please use "mt-tabs" instead\.\n(?!It is not converted)/ }],
        },
    ],
});
