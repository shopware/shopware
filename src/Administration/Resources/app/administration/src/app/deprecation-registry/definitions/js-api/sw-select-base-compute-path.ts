import { jsApiMigration, memberCall, reference } from '../helpers';

export default jsApiMigration({
    id: 'js-api.sw-select-base.compute-path',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'The computePath helper in sw-select-base is deprecated. Use Element.contains() against the event target.',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.7.md#administration' }),
    ],
    usage: [
        memberCall({
            from: 'computePath',
            to: 'event.target instanceof Node && this.$el.contains(event.target)',
            fix: 'manual',
        }),
    ],
});
