[titleEn]: <>(Refactoring routing and route scope interaction)

The route scopes are now more integrated into the routing process. They are no longer a separate layer of checks but are now intertwined with the routing, authentification and context resolving process. A typical request therefore is handled like this:

1. Symfony resolves a controller and loads the corresponding route scope annotation.
2. The Shopware authentication listeners check if the scope needs a valid authentication and authenticates.
3. The Shopware context resolvers check if the scope needs a context and removes them.
4. The Shopware route scope validator checks if the prior listeners worked correctly.    

This process ensures that the stack is always in a correct state when the controller action is invoked.
