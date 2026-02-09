This component is special because it's going to be injected inside `index.html` and `index.html.twig` where Vue hasn't been mounted yet.
So it works a bit differently:

-   no usage of Twig because there's no loader in vite outside of Vue components. For `index.html` vite uses EJS.
-   no usage of SCSS because it's easier to inject styles without transpilation
