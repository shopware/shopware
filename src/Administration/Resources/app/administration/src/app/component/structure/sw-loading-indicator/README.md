This component is special because it's going to be injected inside `index.html` and `index.html.twig` where Vue hasn't been mounted yet.
So it works a bit differently:

- no usage of `.twig` because there's no loader in vite outside of Vue components
- no usage of `.scss` because of the same reason
