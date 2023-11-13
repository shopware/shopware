# Final and internal annotation

We use `@final` and `@internal` annotations to mark classes as final or internal. This allows us to mark services and classes as public or private API and to define which breaking changes can be expected.

## Final

We mark classes as `@final` when developers can use the class but should not extend it. 

Following changes of the class are allowed:
- Adding new public methods/properties/constants
- Adding new optional parameters to public methods
- Protected and private methods/properties/constants can be changed without any restrictions.
- Widening the type of public method params

Following changes of the class are not allowed:
- Removing public methods/properties/constants
- Removing public methods parameters
- Narrowing the type of public methods/properties/constants

Due to the fact that we "only" mark the classes as `@final` via doc annotation, it is possible for developers to extend the base class and replace the service in the DI container. This is not recommended and should be avoided. But it is possible and without any guarantees.

## Internal

We mark classes as `@internal` when the class is private API and should not be used or extended by other developers.

This means that we can change the class without any restrictions and we also can remove the class without any deprecation.

Due to the fact that we "only" mark the class as `@internal` via doc annotation, it is possible for developers to use the class or replace the service. This is not recommended and should be avoided. But it is possible and without any guarantees.
