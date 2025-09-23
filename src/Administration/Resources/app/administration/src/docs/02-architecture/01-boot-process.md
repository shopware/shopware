# Boot Process (Skeleton)

- Sequence overview (add Mermaid sequence diagram later)
  - Browser requests Admin URL
  - PHP/Twig renders `index.html.twig` injecting:
    - Runtime configuration (API endpoints, feature flags)
    - Auth / context tokens (`apiContext`, `appContext`)
  - Global `Shopware` object constructed
  - JS bundles loaded (vendor, runtime, app, module chunks)
  - Initializers execute (registry population, service setup)
  - Plugins get injected
  - Router resolves initial route → module activation
  - Root Vue instance mounts → first render
- Performance considerations:
  - Code splitting strategy / lazy component chunks
  - Preloading critical modules
- Error handling early phase (fallback UI / white screen mitigation)
