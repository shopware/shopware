# Internal

All classes and elements (methods, properties, constants) that are defined as protected or public are initially Public API for third party developers.

The Shopware Public API must be kept compatible with each release. This means that the following must not change for third party developers in a minor release:
- The developer uses a service to use certain functions.
- The developer decorates a service to extend its functionality.
- The developer uses DTO to get or pass data.

There are various other use-cases for third party developers, but the above reflect the standards.

However, if all classes and properties had to be considered public api by us, we would be very limited in our work.

Therefore, we mark the elements that we do not consider to be public API. To do this, we have the following tools at our disposal.

## Decoration pattern
Classes that are intended for **service decoration** are provided with an abstract class. This class is then provided with a `getDecorated` function to pass unimplemented functions directly to the core classes. [Read more](https://github.com/shopware/shopware/blob/trunk/adr/2020-11-25-decoration-pattern.md)

## Final classes
`final` is about inheritance, not about whether a class is Public API. A class can be supported for use by third party developers and still forbid extension.

Use a native `final class` for concrete classes that do not need extension, decoration, proxying, or mocking. This is especially useful for simple value objects, structs, DTO-style classes, and event subscribers. To append data to extensible structs, use the base `Struct` extension mechanism instead of inheritance.

Use `@final` for supported services or other public concrete classes when third party developers may use the class, but must not extend it. If a service is intended to be exchanged via DI decoration, expose a supported abstract decorator contract instead of relying on `extends` from the concrete core service.

Do not add `@final` to classes that are already marked `@internal`. `@internal` is the stronger signal: the class is an implementation detail and not a supported extension or consumption point.

## Internal annotation
Classes where we want to reserve a complete **refactoring** or where we only implemented them to avoid "a big master class" in a domain, we mark with the doc block `@internal`.

`@internal` is about supported use, not only about inheritance. Classes with this annotation may change completely with each release and are therefore not intended to be used, extended, decorated, or referenced by third party developers.

Do not repeat `@internal` on constructors or methods inside an `@internal` class. The class-level marker is enough.

## Internal interfaces
We declare interfaces as `@internal` when we want multiple implementations of a feature or adapter inside core, but do not want third party developers to implement or depend on that contract. A good example of this is the Data Abstraction layer and the Field and FieldSerializer classes. In such areas of the domain we want to reserve optimizations and breaks within minor versions but still be able to work with interfaces and abstract classes.
