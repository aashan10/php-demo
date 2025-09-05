# Installation and Setup

The Elementary framework uses Docker for a consistent and easy-to-manage development environment. All required services are defined in the `docker-compose.yml` file.

## Prerequisites

Before you begin, ensure you have the following installed on your system:

- [Docker](https://www.docker.com/get-started)
- [Docker Compose](https://docs.docker.com/compose/install/)

## Getting Started

1.  **Clone the Repository**

    If you haven't already, clone the project repository to your local machine.

2.  **Build and Run the Services**

    Navigate to the project root directory in your terminal and run the following command to build the images and start the services in the background:

    ```bash
    docker-compose up -d --build
    ```

## Available Services

This command will start the following services:

-   **`nginx`**: The web server. Your application will be accessible at [http://localhost](http://localhost).
-   **`php`**: The PHP-FPM service that executes the application code.
-   **`mysql`**: The MySQL database service.
-   **`phpmyadmin`**: A web-based database management tool, accessible at [http://localhost:8080](http://localhost:8080).
-   **`cli`**: A container for running command-line operations, such as Composer or the framework's custom commands.

## Accessing the Application

Once the services are running, you can access your application by navigating to [http://localhost](http://localhost) in your web browser.

## Stopping the Services

To stop all running services, you can run:

```bash
docker-compose down
```