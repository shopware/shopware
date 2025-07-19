This documentation explains how to create and label issues in Shopware's GitHub backlog as part of planning and delivery. Topics covered here include:
* creating a new issue
* applying Issue Types and labels
* the types of labels we use, and who uses them

## Table of contents

- [Table of contents](#table-of-contents)
- [Creating a new issue](#creating-a-new-issue)
  - [Bug report](#bug-report)
  - [Epic](#epic)
  - [Technical TODO](#technical-todo)
  - [Story](#story)
- [Using labels](#using-labels)
    - [Labels and Product Quality Operations daily triage](#labels-and-product-quality-operations-daily-triage)
    - [Label taxonomy](#label-taxonomy)

**Important for contributors and users**: The [Shopware backlog](https://github.com/shopware/shopware/issues) is where you may log *technical* (ie code- or repository-related) Improvements, Tasks, and feature requests, as well as bug reports. If you have an idea for the Shopware *product*, please log it in our [Product Ideas & Feedback portal](https://feedback.shopware.com/forums/942607-shopware-6-product-feedback-ideas).

Shopware employees—including product managers and engineering leads—also use this backlog to file User Stories and Epics for Shopware delivery teams to break down and ship. 

## Creating a new issue

To make a new issue and add it to the [Shopware backlog](https://github.com/shopware/shopware/issues), click the green "New issue" button and choose the right template for your idea or need. You have four options.

 ### Bug report
 
 For reporting errors or flaws in Shopware code that are leading to incorrect or unexpected behavior. This issue type will ask you several questions about your issue. Please answer the questions providing as much context as you can, so that we may more easily understand and address your concern. At the bottom of the report, please choose the `Bug` Issue type so that we can clearly identify the issue as a `Bug`. Self-assign it if you plan to resolve the bug yourself; otherwise skip this step. 

**Shopware Customer Support team members**: please add the [domain/customer-support](https://github.com/shopware/shopware/labels/domain%2Fcustomer-support) label to the bug report you've just created, so we can flag it appropriately as a CS request. [[GitHub documentation on applying labels]](https://docs.github.com/en/issues/using-labels-and-milestones-to-track-work/managing-labels#applying-a-label)

When you're done filling out the bug report, click the green "Create" button. That's it!

### Epic

This issue template is for filing a request of substantial complexity. By "substantial," we mean that it will likely take 1-3 months to complete. We don't expect contributors or users to accurately estimate how long their requests will take to deliver. But if you have a fairly good sense that your ask may take multiple steps to achieve, or involves various moving parts, feel free to use this issue type. 

**Shopware Product Managers (PMs) and Delivery Teams**: Please use the Epic template to create tracking issues for both feature and technical Epics, and respond to all of the template questions. 

Some questions in the Epic template are required so that we may collect essential context to understand the ask and start thinking about how to measure impact. Other questions are voluntary for users and contributors to answer, but we encourage you to provide details there as well. Members of the Shopware prod/eng organization should answer all template questions.

When you're done filling out the Epic, click the green "Create" button. 

Shopware PMs and delivery teams should then break down the Epics into smaller GitHub child tasks and [add them to the Epic issue](https://docs.github.com/en/issues/tracking-your-work-with-issues/using-issues/adding-sub-issues) so that we can cluster related items together. Then:
* [[apply the correct `domain/` label]](https://docs.github.com/en/issues/using-labels-and-milestones-to-track-work/managing-labels#applying-a-label) from our [label list](https://github.com/shopware/shopware/labels) to the Epic as well as each child issue, so that they get added to the responsible team's issue backlog. 
* [choose the correct Issue Type](https://docs.github.com/en/issues/tracking-your-work-with-issues/using-issues/editing-an-issue#adding-or-changing-the-issue-type) from the provided options to all of the child issues you create, so we can differentiate and filter issues quickly.
* add the appropriate priority label to the Epic and related child issues that you create: [`priority/critical`](https://github.com/shopware/shopware/labels/priority%2Fcritical), [`priority/high`](https://github.com/shopware/shopware/labels/priority%2Fhigh), or [`priority/low`](https://github.com/shopware/shopware/labels/priority%2Flow). 

### Technical TODO

This issue template is for filing Improvements and Tasks related to technical work, such as (but not only): refactoring code, adding tests, improving performance, addressing security needs, conducting a spike, changing/adding/removing configuration, or adding developer documentation. Anyone, including Shopware users and contributors, may use this template to log any technical change or non-coding task you think we should make. 

Once you've filled out the template, click the green "Create" button to file your issue.

**Shopware Delivery Teams**: Use the Technical TODO issue template to record both technical Improvements and Tasks. To differentiate Improvements from Tasks, simply [choose the correct Issue Type](https://docs.github.com/en/issues/tracking-your-work-with-issues/using-issues/editing-an-issue#adding-or-changing-the-issue-type) at the bottom of the template. Then:
* [[apply the correct `domain/` label]](https://docs.github.com/en/issues/using-labels-and-milestones-to-track-work/managing-labels#applying-a-label) from our [label list](https://github.com/shopware/shopware/labels) to the new issue so that it gets added to the responsible team's backlog. 
* add the appropriate priority label: [`priority/critical`](https://github.com/shopware/shopware/labels/priority%2Fcritical), [`priority/high`](https://github.com/shopware/shopware/labels/priority%2Fhigh), or [`priority/low`](https://github.com/shopware/shopware/labels/priority%2Flow). If you're not sure, discuss it with your team and/or engineering lead first.

### Story

This issue template is for filing classic user stories: As a [user persona], I would like to [goal], So that I can [benefit in some way]. This issue template is **primarily for Shopware Product Managers to use**, because they are responsible for developing our roadmap. 

To use this template, simply create the user story and acceptance criteria, [choose the `Story` Issue Type](https://docs.github.com/en/issues/tracking-your-work-with-issues/using-issues/editing-an-issue#adding-or-changing-the-issue-type) at the bottom of the template, and click the green "Create" button to file it. Then:
* [[apply the correct `domain/` label]](https://docs.github.com/en/issues/using-labels-and-milestones-to-track-work/managing-labels#applying-a-label) from our [label list](https://github.com/shopware/shopware/labels) to the new Story issue so that it gets added to the responsible team's backlog. 
* Product Managers should add the appropriate priority label: [`priority/critical`](https://github.com/shopware/shopware/labels/priority%2Fcritical), [`priority/high`](https://github.com/shopware/shopware/labels/priority%2Fhigh), or [`priority/low`](https://github.com/shopware/shopware/labels/priority%2Flow). If you're not sure about the priority yet, discuss it with your team and/or engineering lead first.

## Using labels

As indicated above, we use [labels](https://github.com/shopware/shopware/labels) to organize and categorize backlog issues. Labels follow a `category/item` format.

### Labels and Product Quality Operations daily triage

Customers, partners, and Shopware employees submit bug tickets via GitHub. Every workday morning, the Product Quality Operations team screens new bug tickets—identified by the `needs-triage` label, applied automatically—and assigns a priority to them based on their impact. They apply two labels:
* a `domain/` label to push the Bug issue to the owning delivery teams
* a `priority/` label to inform the team about whether the severity of the Bug is `critical` (to address and resolve immediately), `high`, or `low`.

Once a Bug features the `domain/` label, it will automatically appear in the corresponding team's backlog. At this point it's the team's responsibility to review, plan, and address it.

### Label taxonomy

The task of adding descriptions to every [label](https://github.com/shopware/shopware/labels) is a work-in-progress. Here is an overview of label families and good-to-know one-offs:
* `area/`: for cross-cutting, recurring attributes or themes.
* `backport-`: overseen by Product Operations, these trigger workflows to backport changes.
* `component/`: our extensive list of component offerings replicates what Shopware delivery teams have historically used in Jira to organize their backlogs. In time, they may choose to remove these labels.
* `Default (Platform)`: applies to all newly created GitHub issues and drives Jira syncing. Will deprecate when all Shopware delivery teams have migrated to GitHub.
* `Domain:` or `domain/`: used by Shopware delivery teams to push items to their backlogs. The domain names refer to the different product areas for which these teams are responsible.
* `domain/customer-support`: used by the Shopware Customer Support team to call out label bug issues they file, so that these may be escalated and prioritized accordingly.
* `Extension:`: pulls items related to Shopware extensions into the shopware/shopware issues backlog.
* `good first issue`: issues friendly to brand-new hires or contributors.
* `needs-triage`: applies automatically to every newly created issue. Helps Product Quality Ops to identify what they must review during their daily triage.
* `no-sync` (temporary): a label that stops the creation of a matching issue in Shopware Jira. You must apply this label manually. Will deprecate when all Shopware delivery teams have migrated to GitHub.
* `pr/`: to identify pull request status.
* `priority/`: to identify issue priority. Product Quality Ops applies this to report bug severity. Delivery teams may use it to prioritize other issues. 
* `service/`: similar to the `domain/` label, but related to Shopware service teams.
* `Status:`: syncs issue status to Jira. Will deprecate when all Shopware delivery teams have migrated to GitHub.
* `testing`: relates to different testing needs and types.