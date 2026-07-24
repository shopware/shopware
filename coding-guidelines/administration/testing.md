# Administration testing

These rules apply to Jest, Vue Test Utils, and Administration unit/component tests.

- Write Jest tests for new Administration features and bug fixes.
- Put tests next to the code under test with a `.spec.ts` suffix for new TypeScript code.
- Split very large tests into a `.spec/` directory grouped by behavior.
- Test behavior, not Vue internals or implementation details.
- Prefer `shallowMount` for component tests unless child rendering is part of the behavior under test.
- Clean up mounted wrappers in `afterEach()`.
- Use `flushPromises()` after async UI or repository work.
- Keep setup small and scenario-specific. Avoid broad fixture factories that hide the behavior being tested.
- Use existing Administration test helpers and mocks for repositories, services, ACL, and feature flags.
- Cover error scenarios for API services and user-facing save/load flows.
