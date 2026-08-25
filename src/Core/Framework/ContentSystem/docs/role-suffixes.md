# Role Suffixes

A role suffix is a contract, not decoration. This page says what each one promises and what breaks the promise; the naming principles it applies live in [NAMING.md](../NAMING.md).

Some suffixes promise behavior, and a reader is entitled to that promise. A `Validator` here is one of two sanctioned families: a constraint's paired `ConstraintValidator` — the inherited Shopware idiom, one validator per `Constraint` — or a write-boundary subscriber that rejects an invalid write. A service that only computes and returns a report is neither, however validation-shaped it feels. Choose by answering behavioral questions — does it pair with a `Constraint`, does it reject at a boundary, decide a pass/fail predicate, apply a decision it must first resolve — not by reaching for the nearest synonym. The suffix encodes the answer so the next reader does not have to open the file.

A `Registry` is the single authority over a named set and its resolution, so a class that merely looks a value up without owning the set has not earned it. A `Reader` reads one persisted value behind a precedence rule its callers should not have to carry; it promises a single encapsulated read, not a general query service.

A `Normalizer` maps a subject onto the one canonical form of that subject, so applying it to its own output changes nothing: `ElementStyleNormalizer` turns an authored `ElementStyle` into the `ElementStyle` a write stores. Idempotence is the promise, and so is staying on the same subject — one that turns a subject into its wire form is an `Encoder`, which is what `LayoutDiagnosticsResultNormalizer` does, a name to correct rather than a precedent to copy.

## The two-model roles

The two-model split adds roles for moving between the models and for the state each side accumulates. Each of these is a promise, not a flavor of "service":

- **`*Codec`** owns both directions of one wire shape for one subject in one class, so the two directions cannot drift apart. A class that goes only one way is a `Decoder` or an `Encoder`.
- **`*Encoder`** turns a subject into its wire form and nothing else. **`Encoded*`** is the paired noun for that wire form held as a value: `ContentPageEncoder` produces an `EncodedContentPage`, and the pair reads as one step because the participle names the output of the verb.
- **`*Preparer`** brings a subject into the state a named downstream stage requires and hands that same subject back: `StoredTreePreparer` takes a stored forest and returns stored forests the rendering steps can run on. Returning them inside a `TreePreparationResult` does not change the subject — a preparer whose steps leave more than one forest behind still owes the caller every one of them, plus what it recorded about them. It decides nothing the caller could not have decided itself; one that decides is a `Planner` or a `Resolver`.
- **`*Lowering`** is a one-way translation from a richer model to a poorer one, a noun because the translation itself is the thing: `ElementLowering` takes a stored forest down onto the rendered one, dropping its data requirements, its context wiring and its attribution; in FULL mode the values selected onto a rendered element are unwrapped out of their `StoredValue`s, while SKELETON mints structure with no property values at all. Information is dropped by design and no inverse exists; a role with an inverse is a `Codec`.
- **`*Planner`** computes a plan and returns it without executing it. `WiringPlanner` decides what wiring applies where; something else applies it, so a `Planner` that also writes has broken its contract.
- **`*Distributor`** hands one thing to many recipients by a rule it does not own. `ContextDistributor` spreads resolved context across the elements that accept it; who accepts what lives in the declarations.
- **`*Factory`** mints instances of exactly the subject it names — `RenderedElementFactory` returns rendered elements. One that also mutates, persists or validates is misnamed.
- **`*Resolver`** turns a request for a value into that value, by whatever lookup that takes. `LoaderInputResolver` produces the `LoaderInputs` a loader needs; it promises the answer, not the mechanism.
- **`*Index`** is lookup by key over an already-computed set. `ResolvedValueIndex` answers "what did we resolve for this key?" and computes nothing on read; a type that computes on read is a `Resolver`.
- **`*Scaffolding`** is an immutable record of structural facts that one stage derives and a later stage consumes. `RenderScaffolding` records whether a virtual root survived a pruning pass and, optionally, the id of the element a partial render extracts — facts the later stage reads instead of re-deriving. It is not a service and not a persisted value.
- **`*Boundary`** is the single place a whole class of change enters the system. `LayoutWriteBoundary` is where a layout write is admitted, and the singular is the point: a second boundary for the same class of change means one of them is misnamed. **`*Context`** in this position is state one write-path participant records for a later one to read instead of re-deriving: `LayoutWriteContext` is what the field serializer memoizes on the write's `Context`, and `ContentLayoutWriteValidator` is the one participant that reads it back.

## No suffix is a role too

A bare plural noun with no suffix — `PlaceholderValues`, `LoaderInputs` on the same pattern — is a value bag: an immutable set of related values that computes nothing on read. The absence of a suffix is what states that role; validation timing is not part of the promise and differs between the two — `PlaceholderValues::from()` validates every entry once on the way in, while `LoaderInputs` checks each value's type in its typed accessor on every read.
