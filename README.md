# Bibliotekos Sistema

**Recommended document root:** serve the `public/` directory as the webserver document root. (The app also contains fallback checks so it can run when served from the project root during development.)

---

**Prerequisites**

- PHP 8.0+ (PHP 7.4 may work but 8.0+ is recommended)
  - Required PHP extensions: `pdo`, `pdo_mysql` (or `pdo_pgsql` if you switch DB), `mbstring`, `openssl`.
- MySQL / MariaDB (or modify `database/migrate.php` for another DB).
- Composer (optional but required if you use the included `vendor/` directory or `phpdotenv`).
- Git (optional) for cloning the repo.

---

## Installation / Setup

1. Clone (or copy) the project to your machine:

```powershell
# PowerShell (Windows)
git clone <repo-url> .
```

```bash
# Bash (Linux / macOS)
git clone <repo-url> .
```

2. Install PHP dependencies (if `composer.json` exists):

```powershell
# PowerShell
composer install;
```

```bash
# Bash
composer install
```

(If `vendor/` is already present, composer install is optional.)

3. Configure environment variables

- Copy the example `.env` (create one if it's missing) at the project root and set DB credentials used by `database/migrate.php` and the app.

Example `.env` content:

```
DB_HOST=localhost
DB_NAME=biblioteka
DB_USER=stud
DB_PASS=stud
```

Place `.env` in the project root (same folder as `database/migrate.php`).

4. Create the database and tables (migration)

The repo includes `database/migrate.php` which reads `database/schema.sql` and will create the DB and tables.

```powershell
# PowerShell (run from project root)
php database/migrate.php;
```

```bash
# Bash
php database/migrate.php
```

You should see `Database setup complete!` on success. If it fails, check `.env` settings and DB user privileges.

---

## Running for Development

### Using PHP built-in server (single-command, cross-platform)

This runs a development server and serves the `public/` folder as document root.

PowerShell (Windows):

```powershell
# From project root
php -S localhost:8000 -t public;
# Open http://localhost:8000/ in your browser
```

Bash (Linux/macOS):

```bash
# From project root
php -S localhost:8000 -t public
# Open http://localhost:8000/ in your browser
```

---

## Adding admin users

A helper CLI script is provided to create administrator accounts without directly manipulating the database. The script is located at `scripts/create_admin.php`.

Quick non-interactive usage:

PowerShell / Bash:
```powershell
php scripts/create_admin.php <username> <password> <FirstName> <LastName>
```

Example:
```powershell
php scripts/create_admin.php admin StrongP@ssword Jonas Jonaitis
```

Interactive usage:

Run the script without arguments to be prompted for the required fields:

```powershell
php scripts/create_admin.php
```