# How to running laravel project

## 1. Clone repository

```bash
git clone https://github.com/Fantocaa/tako-customer-review.git
cd tako-customer-review
```

## 2. Install Composer & NPM

```bash
composer install
```

```bash
npm install
```

## 3. Copy .env

```bash
cp .env.example .env
```

## 4. Make APP key on env

```bash
php artisan key:generate
```

## 5. Artisan migrate database

```bash
php artisan migrate:fresh --database=tako-perusahaan --path=database/migrations/perusahaan
```

```bash
php artisan migrate:fresh --database=tako-customer --path=database/migrations/customer
```

## 6. Make a Seeder

```bash
php artisan db:seed
```

## 7. Running Laravel (backend)

```bash
php artisan serve
```

## 8. Running Frontend

```bash
npm run dev
```

## if want all data customer id 0 do in below

```bash
TRUNCATE TABLE customers_statuses;
```

```bash
TRUNCATE TABLE customer_links;
```

```bash
TRUNCATE TABLE customer_attaches;
```

```bash
TRUNCATE TABLE customers RESTART IDENTITY CASCADE;
```
