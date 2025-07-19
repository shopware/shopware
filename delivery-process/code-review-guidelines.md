# Code Review Guidelines

## Introduction
Code reviews are an essential part of our development process. They help us maintain high-quality code, ensure consistency across the codebase, and foster a collaborative engineering culture. 
A well-executed review not only improves the technical aspects of the code but also enhances knowledge sharing within the team. 
This guide outlines what reviewers should look for, common pitfalls to avoid, and best practices for assigning the right reviewers. 
By following these guidelines, we can make code reviews more effective, reduce unnecessary delays, and create a smoother development experience for everyone involved.

## What do reviewers look for?
* **Readability:** Is the code written in a way that both internal and external developers can easily understand?

* **Functionality:** Does the code behave as the author likely intended? Is this behavior beneficial for our merchants?

* **Security:** Is the code free from vulnerabilities and potential security risks?

* **Design:** Is the code well-structured and placed in the correct domain? Does it follow best practices outlined in our guidelines (Documentation, ADRs and Guides)?

* **Extensibility:** Is the code extensible in the right places?

* **Tests:** Does the code include well-designed and meaningful automated tests?

* **Error Handling:** Does the code provide enough information at runtime when errors occur? Are errors logged properly and exceptions handled in a way that prevents the shop from breaking while still providing sufficient logs for debugging?

* **Performance:** Is the code performant? How does it scale when tested with 100.000 to 1.000.000 data entries?

* **Naming:** Are variable, class, and method names clear and descriptive?

* **Documentation:** Has the relevant documentation (User and Developer Docs, ADRs, Changelog, Swagger.io) been updated accordingly?

* **Breaking Changes:** Does the code introduce breaking changes that could impact other services, apps, plugins, themes, APIs, or existing functionality?

* **API and Cloud First:** Does the code follow an API-first approach and work correctly in a cloud environment? Can it function independently of a storefront, ensuring flexibility for headless and API-driven use cases?

* **A11y (Accessibility):** Does the frontend code follow accessibility best practices? Are UI elements properly labeled and usable for all users, including those relying on assistive technologies?

* **Backward Compatibility:** Since we support the previous major version, is the change important and should be backported?

## What should be avoided?
* **Subjective Opinions:** If the code meets the standards, but you personally prefer a different style, this should not block your colleague’s progress. 
If you want to enforce a specific code style, consider updating PHPStan or other linters so that the style is applied consistently across the codebase.

* **Unjustified Comments:** Always back up your comments with logical explanations or references to documentation. 
Comments like “_I think this would be better_” or “_Maybe this way?_” are not helpful because they force the PR creator to do additional research, slowing down progress. 
If you’re unsure, it’s often better to not comment at all rather than blocking the PR author with unnecessary investigations.

## Who should review?
* Are you working outside your usual domain? If your changes affect a different domain, add the relevant team for review, as they have the most domain knowledge and can provide the most valuable feedback.

* Ensure that the assigned reviewers have the necessary technical expertise for your changes, such as frontend or backend development, static analysis, automated testing, 
cloud, or other relevant areas, to guarantee a high-quality review process and avoid unnecessary back-and-forth. 
If needed, explicitly mention which parts of the code require attention so that experts can focus on specific areas without having to review everything.

* Do your assigned reviewers actually have time? Requesting a review from someone who is overloaded with work or on vacation is not productive.

## Is the PR large, leading to excessive comments and back-and-forth discussions?
If the PR contains significant changes and the review feels like a never-ending ping-pong game, consider doing an in-person review or a pair programming session instead. 
This approach reduces frustration, allows for direct discussions, clarifies existing solutions, and ensures that feedback is clearly communicated.

## Sources & Further Reading
This guide was inspired by [Google’s Engineering Practices documentation](https://google.github.io/eng-practices/review/), which provides a solid foundation for effective code reviews. 
However, my focus here was to tailor these principles specifically to Shopware and our code review process, addressing the unique challenges we face in our daily development work. 
For those who want to explore code review best practices in more detail, I recommend the curated collection of resources in [this repository](https://github.com/joho/awesome-code-review), 
which provides a comprehensive overview of useful articles, tools, and techniques.
