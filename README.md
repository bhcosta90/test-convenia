# Employee Registration API

A Laravel-based REST API focused on employee ("employee") registration and management. Each employee belongs to a specific user, and employee data is only accessible to the authenticated owner. The application also supports CSV bulk import: an authenticated user uploads a CSV file; the system processes it asynchronously and sends an email indicating success or failure. In case of failures, the email includes a CSV attachment listing the records that failed.


## Table of Contents
- [Features](#features)
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Configuration](#configuration)
- [Running the App](#running-the-app)
- [Seeding a User](#seeding-a-user)
- [Authentication](#authentication)
- [API Documentation](#api-documentation)
- [CSV Import Workflow](#csv-import-workflow)
- [Emails & Queues](#emails--queues)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)
- [Project Structure (Short)](#project-structure-short)
- [Tech Stack](#tech-stack)
- [License](#license)


## Features
- Employee registration and CRUD.
- Per-user data isolation: only the logged-in user can access their employees.
- CSV bulk import for employees, initiated by the authenticated user.
- Post-processing email notification with overall status.
  - On errors, an additional CSV attachment with failed rows is sent.
- Modern Laravel 12.x foundation with Pest tests.


## Prerequisites
- PHP 8.3+
- Composer 2.x
- Node.js 18+ (for asset build)
- A database supported by Laravel (SQLite/MySQL/PostgreSQL). SQLite is sufficient for local dev.
- Mail service credentials (for sending notifications) — can be a local mail catcher in development.


## Installation
Run the following commands from the project root:

1. Install PHP dependencies:

   ```bash
   composer install
   ```

2. Run project setup (env, key, migrations, frontend build):

   ```bash
   composer run setup
   ```

This will:
- Create a `.env` (if missing) from `.env.example`.
- Generate the application key.
- Run database migrations.
- Install Node dependencies and build assets.


## Configuration
- Environment variables live in `.env`.
- Ensure the following are set correctly for your environment:
  - `APP_URL`
  - `DB_*` (database connection)
  - `MAIL_*` (mail transport for notifications)
  - Queue connection: `QUEUE_CONNECTION=database` (or another of your choice)

If you are using SQLite locally, a starter file may be created automatically. Confirm the path in `DB_DATABASE` points to an existing file (e.g., `database/database.sqlite`).


## Running the App
For a convenient local dev experience (HTTP server, queue listener, logs, and Vite), use the provided Composer script:

```bash
composer run dev
```

Alternatively, you can run components separately:
- App server: `php artisan serve`
- Queue listener: `php artisan queue:listen --tries=1`
- Logs: `php artisan pail --timeout=0`
- Vite dev: `npm run dev`


## Seeding a User
To create an initial user via database seeders, run:

```bash
php artisan db:seed
```

This will insert example data (including at least one user) so you can authenticate and start using the API.


## Authentication
This project uses token-based authentication. Obtain a token via the authentication endpoints and include it in subsequent requests.

- Send the token using an `Authorization: Bearer <token>` header.
- Access to employee resources is always scoped to the authenticated user.

Check the Postman collection for exact auth endpoints and payloads.


## API Documentation
- Swagger UI (OpenAPI) is available at:

  ```
  /api/docs
  ```

  When running locally with `php artisan serve`, access it at `http://127.0.0.1:8000/api/docs`. Use it to explore endpoints and run tests interactively.

- A Postman collection is also provided under:

  ```
  storage/docs
  ```

- Import the collection in Postman to explore and execute requests.
- Endpoints cover authentication, employee management, and CSV bulk import.


## CSV Import Workflow
1. The authenticated user uploads a CSV file with employee data.
2. The file is queued for background processing.
3. When processing finishes:
   - If all rows succeed, the user receives a success email.
   - If any rows fail, the user receives a failure email with a CSV attachment listing all failed rows and reasons.

CSV tips:
- Ensure header names and formats match what the API expects (see Postman examples for reference).
- Use UTF-8 encoding and a comma `,` as the delimiter unless otherwise specified.


## Emails & Queues
- Import processing and notification delivery run via Laravel queues.
- Make sure a queue worker is running in development and production:

  ```bash
  php artisan queue:listen --tries=1
  ```

- Configure your mail transport in `.env` (`MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, etc.).


## Testing
Run the test suite with Composer:

```bash
composer test
```

This will clear config cache and run the test suite (Pest).


## Troubleshooting
- Migrations fail
  - Check your database credentials in `.env` and that the database exists.
- Emails not sending
  - Verify `MAIL_*` env variables and try a local mail catcher.
- Queue jobs not processing
  - Ensure the queue worker is running and `QUEUE_CONNECTION` is set.
- Auth errors
  - Confirm you’re including the `Authorization: Bearer <token>` header.


## Project Structure (Short)
- `app/` — Application code (controllers, jobs, models, etc.)
- `routes/` — API routes
- `database/` — Migrations, factories, seeders
- `storage/docs/` — Postman collection and docs
- `storage/fixtures/` — Example CSV fixtures (e.g., `employees.csv`)
- `tests/` — Test suite (Pest)


## Tech Stack
- Laravel 12.x
- PHP 8.3
- Pest for testing
- JWT-style token auth
- Queued jobs for CSV processing and email notifications


## License
This project is licensed under the MIT License. See the `LICENSE` file if present.
