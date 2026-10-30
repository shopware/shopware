// Plant: base trunk. (1) A new static PriceRoundingHelper copies the logic of
// the CashRounding service and is called from AbsolutePriceCalculator instead
// of injecting CashRounding (DIX-007). (2) A new public EntitySearchCriteriaEvent
// is dispatched in EntitySearcher::search(), which every DAL search passes,
// although EntitySearchedEvent and the Store API extension events exist (DIX-003,
// also DIX-004/DIX-006). Guide di-and-extension-points.
// Expectation: a DIX- finding on the EntitySearcher event; the helper finding is
// expected but not decisive.

import { all, file, findings, grade, majorOrBlocking, rule } from './_lib.ts';

grade((g, out) => {
    g.checkSchemaPersona();

    g.check('persona-attribution', out.persona === 'architecture');
    g.require('dix-rule-finding', findings(out).some(rule('DIX-')));
    g.require(
        'hot-path-finding',
        findings(out).some(
            all(
                rule('DIX-'),
                file(
                    'src/Core/Framework/DataAbstractionLayer/Dbal/EntitySearcher.php',
                    'src/Core/Framework/DataAbstractionLayer/Event/EntitySearchCriteriaEvent.php',
                ),
            ),
        ),
    );
    g.check(
        'static-helper-finding',
        findings(out).some(
            all(
                rule('DIX-'),
                file('src/Core/Checkout/Cart/Price/PriceRoundingHelper.php', 'src/Core/Checkout/Cart/Price/AbsolutePriceCalculator.php'),
            ),
        ),
    );
    g.check('hot-path-major', findings(out).some(all(rule('DIX-'), majorOrBlocking)));
});
