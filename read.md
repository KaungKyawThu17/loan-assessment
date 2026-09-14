# Loan Assessment — Setup and Run

This guide explains how to run the project locally with Laravel Herd.

**Already set up this project?** Keep your existing `.env` file and database. Go to [Run the project](#run-the-project). First-time setup is only needed for a new installation.

## Requirements

- Laravel Herd with PHP 8.4 selected for this project.
- Composer 2 to install PHP packages.
- Node.js 22.12 or newer in the Node.js 22 series, with npm, to install and build frontend packages.
- SQLite for the easiest new setup, or MySQL if you already use it.

Check your tools in the terminal:

```bash
php -v
composer --version
node -v
npm -v
```

## First-time setup

### 1. Open the project and install packages

Open a terminal inside the `loan-assessment` folder. Run all commands in this guide from that folder.

```bash
composer install
npm ci
```

### 2. Create the local settings file

If `.env` does not exist, create it from the example:

```bash
cp .env.example .env
```

Open `.env` in your editor and set:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://loan-assessment.test
```

For a new installation with an empty `APP_KEY`, generate the encryption key:

```bash
php artisan key:generate --no-interaction
```

Keep this key when using an existing database.

### 3. Choose one database

Your `.env` file tells Laravel which database to use. Keep your existing database settings if you already have accounts or loan records.

**Option A: SQLite — simplest for a new installation**

Keep this setting from `.env.example`:

```dotenv
DB_CONNECTION=sqlite
```

Leave `DB_DATABASE` commented out so Laravel uses `database/database.sqlite`. Create the file:

```bash
touch database/database.sqlite
```

SQLite stores the database in this file and needs no separate database server.

**Option B: MySQL**

Start your MySQL service and create an empty database named `loan_assessment` using your database application. Then set these values in `.env`, replacing the username and password with your own:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=loan_assessment
DB_USERNAME=your_mysql_username
DB_PASSWORD="your_mysql_password"
```

### 4. Create the tables and demo accounts

Load your settings and run the migrations. Migrations create or update the database tables:

```bash
php artisan config:clear --no-interaction
php artisan migrate --no-interaction
```

Create the local demo accounts:

```bash
php artisan db:seed --class=AssessmentUserSeeder --no-interaction
```

This seeder works only in the `local` and `testing` environments. Running it again resets the demo accounts' passwords, names and roles, and their customer profile details. Plain `php artisan db:seed` does not create these demo accounts.

### 5. Link the site and build the frontend

If Herd has not already linked this project, run:

```bash
herd link loan-assessment
```

Build the CSS and JavaScript:

```bash
npm run build
```

Open [the login page](http://loan-assessment.test/login).

## Run the project

1. Open Laravel Herd and ensure the site is running.
2. If you use MySQL, ensure the MySQL service is running too.
3. While editing the frontend, run this command and leave the terminal open:

   ```bash
   npm run dev
   ```

4. Open [Loan Assessment](http://loan-assessment.test).

Herd serves the PHP application automatically. `npm run dev` updates frontend assets as you edit them. Press `Ctrl+C` to stop it. To use the site without keeping that command running, run `npm run build` after your frontend changes.

You do not need to repeat the key generation, migrations or demo seeding every time you open the project.

## Demo login accounts

After running `AssessmentUserSeeder`, all four accounts use the password **`LocalDemo!2026`**. The password is case-sensitive.

| Role | Email |
| --- | --- |
| Admin | `admin@loan.test` |
| Loan officer | `officer@loan.test` |
| Customer | `customer1@loan.test` |
| Customer | `customer2@loan.test` |

Registering through the website creates a customer account.

## Run tests

```bash
php artisan test --compact
```

Tests use a separate SQLite database in memory. PHP needs SQLite support even when your development database uses MySQL.

## Common problems

- **Admin cannot log in:** Check the email and password above. Confirm `.env` points to the database where you ran `AssessmentUserSeeder`. If the demo accounts are missing, run the seeder from step 4.
- **Database connection error:** Start MySQL if you use it, and check the database name, username and password in `.env`.
- **Missing database tables:** Check your database settings, then run `php artisan migrate --no-interaction`.
- **Changed `.env`, but old settings still apply:** Run `php artisan config:clear --no-interaction`.
- **Missing Vite manifest or frontend changes:** Run `npm run build`, or keep `npm run dev` running while developing.

Use `migrate` for normal setup and updates. `migrate:fresh` deletes existing tables and their data.
