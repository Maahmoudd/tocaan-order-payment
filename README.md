# Order Payment API

Assessment-ready REST API demonstrating JWT authentication, order management,
idempotent payment processing, and strategy-based simulated payment gateways.

## Stack

- PHP 8.3+ natively or PHP 8.5 through Laravel Sail
- Laravel 13
- MySQL 8.4 LTS
- Redis and Mailpit when using Sail
- `tymon/jwt-auth`
- Pest 4

The application supports two runtime modes:

- **Laravel Sail:** the application, MySQL, Redis, and Mailpit run in Docker.
- **Native:** PHP and Composer run on the host and connect to a local MySQL
  server. Redis and Mailpit are optional because the native profile defaults to
  file cache/session, synchronous queues, and log mail.

Environment templates:

- `.env.sail.example` for Sail
- `.env.native.example` for host PHP
- `.env.example` mirrors the native profile for Laravel/Composer defaults

## Requirements

- Git
- Node.js 20.19+ or 22.12+ when building frontend assets
- One of:
  - Docker Engine with Docker Compose for Sail
  - PHP 8.3+, Composer 2, and MySQL 8 for native execution

Required native PHP extensions include PDO MySQL, Mbstring, OpenSSL,
Tokenizer, XML, Ctype, Fileinfo, and DOM.

## Choose a runtime

Clone the repository, enter it and install dependencies:

```bash
git clone https://github.com/Maahmoudd/tocaan-order-payment.git order-payment-api
cd order-payment-api
composer install
```

### Option A: Laravel Sail


Create the Sail environment and add the alias once per shell profile or terminal
session:

```bash
cp .env.sail.example .env
alias sail='./vendor/bin/sail'
```

Start the containers and initialize the application:

```bash
sail up -d
sail artisan key:generate
sail artisan jwt:secret
sail artisan migrate --seed
```

The services are then available at:

| Service | URL/connection |
|---|---|
| API | `http://localhost/api/v1` |
| Health check | `http://localhost/up` |
| Mailpit dashboard | `http://localhost:8025` |
| MySQL from host | `127.0.0.1:3306` |
| Redis from host | `127.0.0.1:6379` |

If a host port is already occupied, set the corresponding forwarding variable
in `.env`, for example `APP_PORT=8080` or `FORWARD_DB_PORT=3307`.

Useful lifecycle commands:

```bash
sail ps
sail stop
sail down
sail down -v
```

`sail down -v` also deletes local MySQL and Redis volumes.

### Option B: Native PHP

Install dependencies and create the native environment:

```bash
composer install
cp .env.native.example .env
composer check-platform-reqs
```

Create the development and testing databases with credentials matching `.env`:

```sql
CREATE DATABASE laravel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

For example, using a local MySQL root account:

```bash
mysql -u root -p -e \
  "CREATE DATABASE IF NOT EXISTS laravel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; \
   CREATE DATABASE IF NOT EXISTS testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Update `DB_USERNAME` and `DB_PASSWORD` in `.env` if your local MySQL credentials
differ, then initialize and start the application:

```bash
php artisan key:generate
php artisan jwt:secret
php artisan migrate --seed
php artisan serve
```

The native API is available at `http://localhost:8000/api/v1`.

The native profile does not require Redis or Mailpit:

- `CACHE_STORE=file`
- `SESSION_DRIVER=file`
- `QUEUE_CONNECTION=sync`
- `MAIL_MAILER=log`

To use local Redis or Mailpit, change those `.env` values and ensure the
services are running on the configured host and ports.

## Command reference

Use the command column matching the active runtime.

| Task | Sail | Native |
|---|---|---|
| Install dependencies | `sail composer install` | `composer install` |
| Update dependencies | `sail composer update` | `composer update` |
| Start API | `sail up -d` | `php artisan serve` |
| Run migrations | `sail artisan migrate` | `php artisan migrate` |
| Rebuild and seed | `sail artisan migrate:fresh --seed` | `php artisan migrate:fresh --seed` |
| Run seeders | `sail artisan db:seed` | `php artisan db:seed` |
| List routes | `sail artisan route:list` | `php artisan route:list` |
| Generate JWT secret | `sail artisan jwt:secret` | `php artisan jwt:secret` |
| Open MySQL | `sail mysql` | `mysql -u root -p laravel` |
| Run tests | `sail pest` | `vendor/bin/pest` |
| Check style | `sail pint --test` | `vendor/bin/pint --test` |
| Apply formatting | `sail pint` | `vendor/bin/pint` |

## Authentication

JWT access tokens are returned by registration and login. Send the token on
protected endpoints:

```http
Authorization: Bearer <access-token>
Accept: application/json
```

Generate or rotate the local signing secret with the command for your runtime:

```bash
# Sail
sail artisan jwt:secret

# Native
php artisan jwt:secret
```

Rotating `JWT_SECRET` invalidates all existing tokens.

Default token settings:

- Access token TTL: 60 minutes
- Refresh window: 20,160 minutes (14 days)
- Blacklisting enabled for refresh/logout invalidation

The examples below use Sail's `http://localhost` base URL. When running
natively, use `http://localhost:8000`.

## Postman collection

Import the published collection directly into Postman:

**[Order Payment API Postman collection](https://raw.githubusercontent.com/Maahmoudd/tocaan-order-payment/main/postman/Order-Payment-API.postman_collection.json)**

The collection includes request and response examples for every endpoint,
collection variables for both runtime modes, and scripts that automatically
save the JWT, created order ID, and created payment ID. Its default
`base_url` is `http://localhost/api/v1`; change it to
`http://localhost:8000/api/v1` for native PHP.

Suggested workflow:

1. Run `Register` (or `Login`).
2. Run `Create Order`, `Update Order`, then `Confirm Order`.
3. Run `Process Stripe Payment`.
4. Use the list and detail requests with the automatically saved IDs.
5. Run `Logout` last.

Example registration:

```bash
curl --request POST http://localhost/api/v1/register \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data '{
    "name": "API User",
    "email": "user@example.com",
    "password": "password",
    "password_confirmation": "password"
  }'
```

Example login:

```bash
curl --request POST http://localhost/api/v1/login \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data '{
    "email": "user@example.com",
    "password": "password"
  }'
```

## Response envelope

Successful responses:

```json
{
  "success": true,
  "message": "Operation completed successfully.",
  "data": {},
  "meta": {}
}
```

Error responses:

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {}
}
```

Common status codes are `200`, `201`, `401`, `403`, `404`, `409`, and `422`.

## Endpoint reference

All protected routes require a Bearer token.

### Authentication

| Method | Endpoint | Protected | Description |
|---|---|---:|---|
| POST | `/api/v1/register` | No | Register and receive a JWT |
| POST | `/api/v1/login` | No | Authenticate and receive a JWT |
| POST | `/api/v1/logout` | Yes | Invalidate the current token |
| POST | `/api/v1/refresh` | Token required | Refresh a refreshable token |
| GET | `/api/v1/me` | Yes | Return the authenticated user |

### Orders

| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/v1/orders` | Create a pending order |
| GET | `/api/v1/orders` | List the authenticated user's orders |
| GET | `/api/v1/orders/{order}` | Show an owned order |
| PUT/PATCH | `/api/v1/orders/{order}` | Update an owned pending order |
| POST | `/api/v1/orders/{order}/confirm` | Confirm an owned pending order |
| POST | `/api/v1/orders/{order}/cancel` | Cancel an owned pending order |
| DELETE | `/api/v1/orders/{order}` | Soft-delete an owned pending order |

Order list query parameters:

- `status`: `pending`, `confirmed`, or `cancelled`
- `per_page`: 1–100; default 15

Create-order payload:

```json
{
  "notes": "Leave at reception",
  "items": [
    {
      "product_name": "Mechanical Keyboard",
      "quantity": 2,
      "unit_price": "99.95"
    }
  ]
}
```

`subtotal` and `total_amount` are always calculated server-side using exact
decimal arithmetic. Client-supplied totals are ignored.

Order rules:

- Only the owner may view, update, or delete an order.
- Only pending orders may be updated or deleted.
- Orders with payments cannot be deleted.
- Generic updates may change `notes` or replace `items`.
- Confirmation and cancellation use explicit transition endpoints.
- Orders with payment attempts cannot be cancelled.

### Payments

| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/v1/orders/{order}/payments` | Process a payment for a confirmed order |
| GET | `/api/v1/orders/{order}/payments` | List payments for an owned order |
| GET | `/api/v1/payments` | List the authenticated user's payments |
| GET | `/api/v1/payments/{payment}` | Show an owned payment |

Payment list query parameters:

- `gateway`: `credit_card`, `paypal`, or `stripe`
- `status`: `pending`, `processing`, `successful`, `failed`, or `unknown`
- `per_page`: 1–100; default 15

Order-payment list supports `per_page`.

Gateway payload examples:

```json
{
  "gateway": "credit_card",
  "payload": {
    "card_token": "tok_example"
  }
}
```

```json
{
  "gateway": "paypal",
  "payload": {
    "paypal_order_id": "PAYPAL-ORDER-ID"
  }
}
```

```json
{
  "gateway": "stripe",
  "payload": {
    "payment_method_id": "pm_example"
  }
}
```

Payment rules:

- Every payment request must include a unique `Idempotency-Key` header.
- Reusing the same key returns the original payment instead of charging again.
- The order must be confirmed.
- Only the order owner can process or view its payments.
- The payment amount always comes from the order's server-calculated total.
- A successful or unresolved payment blocks additional attempts for the order.
- Definitive declines may be retried with a new idempotency key.
- Unexpected provider errors are stored as `unknown` and require reconciliation
  before another attempt is allowed.
- Sensitive request tokens/references are not stored in payment metadata.
- Provider response metadata is stored internally but not exposed by API
  resources.
- Authentication and payment creation routes are rate limited.
- The included gateways are simulated local adapters for assessment and
  testing only. They do not contact Stripe, PayPal, or a card processor.

Example:

```bash
curl --request POST http://localhost/api/v1/orders/1/payments \
  --header 'Authorization: Bearer <access-token>' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'Idempotency-Key: 43f33ec3-57bd-4ea2-a07a-f9ac8840a3ed' \
  --data '{
    "gateway": "stripe",
    "payload": {
      "payment_method_id": "pm_example"
    }
  }'
```

## Adding a payment gateway

Gateway selection uses the strategy pattern. Adding a gateway requires one
class and one configuration entry.

1. Create a class under `app/Payments/Gateways`.
2. Implement `App\Contracts\PaymentGatewayInterface`.
3. Implement `charge`, `refund`, and `getGatewayName`.
4. Add the class to `config/payment.php`:

```php
'gateways' => [
    // Existing gateways...
    'new_gateway' => NewGateway::class,
],
```

The manager, payment service, and controller do not need changes. If the new
gateway requires different request fields, add its validation rules to
`StorePaymentRequest`.

After changing configuration:

```bash
# Sail
sail artisan config:clear
sail pest

# Native
php artisan config:clear
vendor/bin/pest
```

## Database

Run pending migrations:

```bash
# Sail
sail artisan migrate

# Native
php artisan migrate
```

Rebuild and seed the development database:

```bash
# Sail
sail artisan migrate:fresh --seed

# Native
php artisan migrate:fresh --seed
```

Run seeders without rebuilding:

```bash
# Sail
sail artisan db:seed

# Native
php artisan db:seed
```

Open MySQL:

```bash
# Sail
sail mysql

# Native
mysql -u root -p laravel
```

The automated test suite uses an isolated in-memory SQLite database configured
by `phpunit.xml`, so it runs consistently in Sail, native PHP, and CI without
depending on the development database.

## Tests and code style

Run the complete Pest suite:

```bash
# Sail
sail pest

# Native
vendor/bin/pest
```

Run one suite:

```bash
# Sail
sail pest tests/Feature/OrderTest.php

# Native
vendor/bin/pest tests/Feature/OrderTest.php
```

Run style checks:

```bash
# Sail
sail pint --test

# Native
vendor/bin/pint --test
```

Apply formatting:

```bash
# Sail
sail pint

# Native
vendor/bin/pint
```

Current coverage includes authentication, order CRUD and totals, authorization,
explicit order transitions, payment idempotency, duplicate-payment prevention,
gateway management, charge/refund strategies, rate limiting, and failure paths.

GitHub Actions runs Composer validation, Pint, and the complete Pest suite on
pushes and pull requests. The same local checks can be run with:

```bash
composer quality
```

## Project structure

```text
app/
├── Contracts/             Payment gateway contract
├── Enums/                 Order and payment statuses
├── Exceptions/            API and business-rule exceptions
├── Http/
│   ├── Controllers/Api/V1
│   ├── Requests/
│   ├── Resources/
│   └── Traits/
├── Payments/
│   ├── Gateways/
│   └── PaymentGatewayManager.php
├── Policies/
├── Services/
└── ValueObjects/
```

Controllers handle HTTP concerns and delegate business logic to services.
Policies enforce ownership. API resources serialize responses. Database writes
that change order/payment state use transactions and row locks.
