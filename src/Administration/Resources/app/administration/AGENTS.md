# Shopware 6 Administration - AGENTS.md

> **Full Documentation**: See `technical-docs/` for comprehensive guides
> **Specific Areas**: See AGENTS.md in `src/core/`, `src/app/`, `src/module/`, `test/`

## File Structure
```
technical-docs/     # Full technical documentation
src/
├── core/               # Vue indepedent code, Framework, repositories, services (AGENTS.md)
|   ├── application.ts  # Application bootstrap (AGENTS.md)
|   └── shopware.ts     # Global Shopware object in window (AGENTS.md)
├── app/                # Vue specific code, UI, components, stores (AGENTS.md)
│   ├── init/           # Boot sequence (AGENTS.md)
│   ├── component/      # Global components (AGENTS.md)
│   └── store/          # Pinia stores (AGENTS.md)
└── module/             # Business modules (AGENTS.md)
test/                   # Only setup files, helper and mocks for tests (AGENTS.md)
```

## Technologies
```
# Core
TypeScript          # Main programming language
JavaScript          # Was used in legacy code
Vue 3               # Components get compiled to Vue 3 components
Twig.JS             # Templating engine to allow extensible Vue components
Pinia               # State management
Vue Router          # Routing
Axios               # HTTP client
Vite                # Build tool
Jest                # Testing framework
```

## Special differences to regular Vue projects

- **Component Factory and Runtime Vue Components**: The application uses a component factory that allows dynamic extensibility. This factory creates real Vue components at runtime, which is why the project does not use Single File Components (SFCs). Instead, components are registered dynamically, enabling plugins and extensions to modify or extend existing components seamlessly.
  - **Reference**: See `src/Administration/Resources/app/administration/technical-docs/03-extensibility/` for details on the component factory and extensibility.

- **Special Boot Sequence**: The boot process is tailored to the Shopware ecosystem. It includes steps such as `initState`, `registerConfig`, and `initializeFeatureFlags`. The sequence dynamically imports core modules like `core/shopware.ts` and `app/main.ts`, initializes the dependency injection container, and sets up services and plugins. The Twig shell is used to inject runtime configurations before the Vue.js application is bootstrapped.
  - **Reference**: See `src/Administration/Resources/app/administration/technical-docs/02-architecture/01-boot-process.md` for a detailed overview of the boot sequence.

- **Global Shopware Object**: A global `Shopware` object is created during the boot process. This object acts as the central point for accessing services, factories, and the dependency injection container. It is initialized in `core/shopware.ts` and is available throughout the application.
  - **Reference**: See `src/Administration/Resources/app/administration/technical-docs/02-architecture/03-module-system.md` for more information on the global Shopware object.

## Coding guidelines
- Write Jest tests for all new features and bug fixes
  - Locate tests in the same folder as the code they are testing, using the `.spec.js` suffix
- Use TypeScript for all new code
- Do NOT introduce breaking changes to public APIs without prior discussion
- Follow existing code style and patterns
- Use the provided linting and formatting scripts (see below)

## Scripts
Run the composer commands in the root of the repository. These commands are wrapper scripts around the NPM scripts.

```bash
# Linting
composer eslint:admin # Run ESLint
composer eslint:admin:fix # Run ESLint with --fix
composer stylelint:admin # Run Stylelint
composer stylelint:admin:fix # Run Stylelint with --fix

composer format:admin # Format code with Prettier
composer format:admin:fix # Format code with Prettier and --write

# Tests
composer admin:unit # Run unit tests
composer admin:unit:watch # Run unit tests in watch mode

# Build
composer build:js:admin # Build the administration
```

**See**: `src/Administration/Resources/app/administration/technical-docs/` for architecture, patterns, and detailed guides
