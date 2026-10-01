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
- Register a globally available framework component (for example `sw-block`) in `test/_setup/prepare_environment.js` through `config.global.stubs` with its real implementation, not through `config.global.components`. Vue Test Utils skips `app.component()` for any key that also appears in `stubs`, so the two are mutually exclusive, and only the `stubs` entry keeps the component real under `shallowMount`.
- Cover error scenarios for API services and user-facing save/load flows.
