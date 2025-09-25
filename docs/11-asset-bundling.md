# Asset Bundling with Vite

The Elementary framework uses Vite for modern, fast, and efficient asset bundling. This allows you to write your frontend assets using modern tools like TypeScript and have them automatically compiled, bundled, and versioned for production.

## How It Works

The integration is handled by a combination of Vite's configuration and a few custom components within the framework:

-   **Vite Configuration (`vite.config.ts`):** This file tells Vite where to find your asset entry points and where to output the compiled files. By default, it looks for assets in the `resources` directory and builds them to `public/build`.
-   **Manifest File:** When you run the build command (`npm run build`), Vite generates a `manifest.json` file in `public/build`. This file contains a map of your original asset files to their final, versioned output files.
-   **`ViteService`:** A service within the framework that knows how to read the `manifest.json` file in production. In a development environment, it points to the Vite development server.
-   **`@asset` Directive:** A custom Cigg templating directive that provides the correct HTML tags (`<script>` and `<link>`) for a given asset entry point, automatically handling the difference between development and production environments.

## Getting Started

1.  **Install Dependencies:**

    If you haven't already, install the required Node.js packages:

    ```bash
    npm install
    ```

2.  **Running the Dev Server:**

    To work on your assets in a development environment with Hot Module Replacement (HMR), run the Vite dev server:

    ```bash
    npm run dev
    ```

    This will start a server (usually on `https://localhost:5173`). The `@asset` directive will automatically point to this server, so any changes you make to your JS or CSS files will be reflected in the browser instantly.

3.  **Building for Production:**

    When you are ready to deploy your application, you need to build the final, optimized assets:

    ```bash
    npm run build
    ```

    This command will create the `public/build` directory with your compiled and hashed assets, along with the `manifest.json` file.

## Usage

To include your assets in a template, use the `@asset` directive in your layout file (e.g., inside the `<head>` tag). You must pass the full path to the entry point file.

```html
{{-- templates/layouts/guest.cigg --}}

<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'My Awesome App')</title>
    @asset('resources/js/app.ts')
</head>
```

-   **In Development:** This will output the necessary script tags to connect to the Vite dev server.
-   **In Production:** This will read the `manifest.json` and output `<link>` tags for any associated CSS and a `<script type="module">` tag for the compiled JavaScript, all with the correct hashed filenames.
