# Shopware Administration Architecture Documentation

Welcome to the **codebase architecture documentation** for Shopware's Administration interface. This documentation focuses on understanding the unique architectural patterns rather than generic setup tasks (which are covered in the main Shopware documentation).

## ⚡ What Makes Shopware Administration Special

Shopware's administration interface uses unique architectural patterns that differentiate it from typical Vue.js applications. **Understanding these patterns is essential** because they affect every aspect of development:

- **🔧 Evolution of Extension Systems**: Enable the plugin ecosystem that makes Shopware extensible
  - **Current**: Twig blocks + Options API Component Factory (Component.override/extend)
  - **Future**: Native Vue blocks + Composition API Extension mechanism
- **🏭 Factory Pattern Architecture**: Consistent service and component creation across the entire application
- **💉 Dependency Injection**: BottleJS container that enables testing, modularity, and plugin integration
- **📦 Module-Based Architecture**: Self-contained modules that allow features to be developed independently

**Why This Matters**: These patterns aren't just theoretical - they directly impact how you write components, add features, fix bugs, and work with the rest of the team.

## 📚 Documentation Structure

### **Start Here: Essential Knowledge**

### 📚 [01. Getting Started](./01-getting-started/)

**Quick orientation** to understand what makes Shopware's codebase unique.

- Codebase overview and unique architectural patterns
- Extension systems and why they're central to everything

### 🧠 [02. Core Concepts](./02-core-concepts/)

**Essential patterns** that enable 80% of daily development work.

- Extension systems (current and future) - the heart of Shopware's architecture
- Factory patterns and dependency injection in practice
- Module architecture and data patterns

### 🏗️ [03. Architecture](./03-architecture/)

**Practical deep dive** into how architectural decisions affect your daily work.

- Application bootstrap and dependency injection
- Vue.js integration and routing architecture
- State management and API layer design

### **Contributing & Advanced**

### 🔧 [04. Contributing](./04-contributing/)

**Everything you need** to contribute effectively to the codebase.

- Development workflow and architectural review process
- Testing strategies for Shopware's patterns
- Code standards and team practices

### 🚀 [05. Advanced Topics](./05-advanced-topics/)

**Complex patterns** for experienced contributors and architectural changes.

- Extension system migration and complex patterns
- Performance optimization and debugging
- Module federation and advanced architectural topics

## Target Audience

- **New internal developers** joining the Shopware administration team
- **Existing team members** working on architectural changes
- **Anyone** contributing to the core administration codebase

## Prerequisites

- Strong Vue.js 3 and TypeScript knowledge
- Understanding that this focuses on **architectural patterns**, not basic setup
- Familiarity with the official [Shopware developer documentation](https://developer.shopware.com/docs/)

## What This Documentation Covers

- **Architectural patterns** unique to Shopware's administration
- **Practical guidance** for daily development work
- **Team practices** for effective collaboration
- **Advanced topics** for complex architectural work

## What This Documentation Does NOT Cover

- Development environment setup (see [official docs](https://developer.shopware.com/docs/guides/installation/))
- Basic Vue.js or TypeScript tutorials
- Generic frontend development practices
- External plugin development (this is for core contributors)

---

*This documentation focuses on the essential architectural knowledge needed for contributing to Shopware's administration interface.*
