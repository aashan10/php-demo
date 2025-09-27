# The Elementary Framework

Elementary is a modern, high-performance PHP framework built from the ground up with **zero production dependencies**. It is designed for developers who value speed, simplicity, and full-stack control. While it includes all the essential features of a modern framework—such as a dependency injection container, a fluent router, and a lightweight Active Record implementation—it stands out with its unique, custom-built components:

-   **Spark:** A reactive, server-side component system inspired by Laravel Livewire, allowing you to build dynamic interfaces with minimal JavaScript.
-   **Cigg:** A custom-built, high-performance templating engine with an intuitive syntax and compile-time optimizations.
-   **Zero-Dependency Architecture:** An incredibly lightweight and fast core, offering maximum performance and complete control over the codebase.
-   **Modern Tooling:** Comes pre-configured with Vite for asset bundling and Tailwind CSS for styling.

This project is set up to run in a Docker environment for easy and consistent development.

## Getting Started

To get the application running, please follow the detailed **[Installation and Setup Guide](./docs/01-installation.md)**.

## Documentation

The framework is broken down into several components. For detailed information on each part of the system, please refer to the documentation below:

| # | Topic                                           | Description                                                                 |
|---|-------------------------------------------------|-----------------------------------------------------------------------------|
| 1 | [Installation & Setup](./docs/01-installation.md) | How to get the project running with Docker.                                 |
| 2 | [Routing](./docs/02-routing.md)                 | How to define application endpoints.                                        |
| 3 | [Controllers](./docs/03-controllers.md)         | How to handle requests and build responses.                                 |
| 4 | [Templating (Cigg)](./docs/04-templating-cigg.md) | How to use the Cigg templating engine to create views.                      |
| 5 | [Dependency Injection](./docs/05-dependency-injection.md) | How to manage class dependencies with the container.                        |
| 6 | [Middleware](./docs/06-middleware.md)           | How to filter HTTP requests entering your application.                      |
| 7 | [Database & Models](./docs/07-database-models.md) | How to interact with the database using models.                             |
| 8 | [Console Commands](./docs/08-console-commands.md) | How to create and run command-line tasks.                                   |
| 9 | [Authentication](./docs/09-authentication.md) | How the authentication system works. |
| 10 | [Cookies & Sessions](./docs/10-cookies-sessions.md) | How cookies and sessions are managed. |
| 11 | [Asset Bundling (Vite)](./docs/11-asset-bundling.md) | How to bundle frontend assets with Vite. |
| 12 | [Components (Cigg)](./docs/12-components.md) | How to create reusable UI components. |
| 13 | [Testing](./docs/13-testing.md) | How to write and run tests with PHPUnit. |
| 14 | [Spark Components](./docs/spark/README.md) | How to build reactive, server-side components. |