# Event Tickets

A Laravel 12 event-registration and ticketing platform with a Filament admin panel, SMS
verification over Twilio, QR-coded e-tickets and a door check-in console.

---

## What it does

| Capability | Detail |
|---|---|
| **Public registration** | Browse published events, pick a ticket type and quantity |
| **OTP verification** | Phone verified by SMS one-time code before tickets are issued |
| **Payments** | Flutterwave v3 checkout (card + mobile money), webhook-confirmed |
| **Ticket references** | Unique, human-readable references such as `EVT-PQ27R6AV` |
| **SMS delivery** | Ticket references sent by Twilio, with full delivery-status tracking |
| **Delivery webhook** | Twilio status callbacks update the SMS audit log |
| **E-ticket by email** | Each ticket emailed with a scannable QR code |
| **QR check-in** | Camera scanner or manual reference entry at the door |
| **Admin dashboard** | Live sales, attendance, SMS health and recent check-ins |
| **CSV export** | Registrations, issued tickets, payments and per-event summary |
| **Staff accounts** | Role-based admin panel access (`admin` / `staff`) |

---

## Requirements

- PHP **8.2+** with `pdo_mysql`, `mbstring`, `openssl`, `curl`, `gd`, `bcmath`, `zip`, `fileinfo`
- Composer 2
- MySQL 8 (or MariaDB 10.6+)
- Node 18+ and npm

---

## Local setup

```bash
composer install
npm install && npm run build

cp .env.example .env
php artisan key:generate

# Create the database, then edit DB_* in .env
mysql -u root -e "CREATE DATABASE event_tickets CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

php artisan migrate
php artisan db:seed          # creates demo events + admin/staff logins
php artisan storage:link
```

Run it:

```bash
php artisan serve
```

| URL | Purpose |
|---|---|
| `/` | Public event listings |
| `/admin` | Filament admin panel |

### Seeded logins

| Email | Password | Role |
|---|---|---|
| `admin@example.com` | `password` | Administrator |
| `staff@example.com` | `password` | Staff |

> Change these immediately. Delete the seeder before any real deployment.

### Twilio

Leave `TWILIO_*` blank and the app still runs end to end — messages are written to
`sms_messages` and `storage/logs/laravel.log` instead of being sent. To send real SMS set:

```dotenv
TWILIO_ACCOUNT_SID=ACxxxxxxxx
TWILIO_AUTH_TOKEN=xxxxxxxx
TWILIO_FROM_NUMBER=+254700000000
# or, instead of a from-number:
TWILIO_MESSAGING_SERVICE_SID=MGxxxxxxxx
```

Point the Twilio messaging service status callback at
`https://your-domain.com/webhooks/twilio/status` so delivery states are recorded.

### Flutterwave payments

Payments use the **Flutterwave v3 API**. Sandbox and live share one host — the API key
prefix (`_TEST`) selects which is used, there is no separate sandbox URL.

Get keys from **Flutterwave Dashboard → Settings → API Keys**, then:

```dotenv
FLW_SECRET_KEY=FLWSECK_TEST-...-X
FLW_SECRET_HASH=<the secret hash from your dashboard>
```

Set your webhook URL to:

```
https://your-domain.com/webhooks/flutterwave
```

> **Check the amount units before going live.** Flutterwave's documentation is
> inconsistent about whether `amount` is major units (`12000`) or minor units (`120000`),
> and it differs by currency. The app stores exactly what it sent on the payment row and
> compares the verified transaction against that value, so **fulfilment is correct either
> way** — but the figure the customer is charged depends on `FLW_AMOUNT_IN_MINOR_UNITS`.
> Run one sandbox transaction and confirm the checkout shows the expected total. If it
> shows 100× too much, flip that setting to `true`.

Useful sandbox card (`4187427415564246`, expiry `09/32`, CVV `828`, OTP `12345`) always
succeeds; `5258585922666506` always fails with insufficient funds. Any M-Pesa number
works in test mode with OTP `123456`.

A ticket type with a price of `0` never requires payment, regardless of its
`requires_payment` setting — useful for free community allocations.

### Mail

Development defaults to `MAIL_MAILER=log`. For production set `MAIL_MAILER=smtp` with your
SMTP credentials.

---

## How the registration flow works

```
Guest submits form
      │
      ▼
RegistrationService::createPending()
  · validates availability (event open, ticket on sale, stock left)
  · reserves stock via ticket_types.quantity_sold
  · creates a registration with status = pending_otp
  · issues an OTP and texts it
      │
      ▼  guest submits the code
RegistrationService::verifyOtp()
  · validates the code (expiry + attempt limits)
  · flips the registration to verified
      │
      ├── price 0 ────────────────────────────────┐
      │                                           │
      └── price > 0                               │
              ▼                                   │
      redirect to /register/{ref}/pay             │
              ▼                                   │
      Flutterwave hosted checkout                 │
              ▼                                   │
      webhook  ─┐                                │
      redirect ─┼─► PaymentService confirms ──┐  │
      reconcile┘   (amount + currency check)  │  │
                                             ▼  ▼
                                      PaymentFulfilmentService
                                             │
                                             ▼
                            RegistrationService::fulfil()
                              · issues one ticket per quantity
                              · texts the references
                              · queues the e-ticket email
```

Tickets are **never** issued before payment settles. If a guest never verifies, the
scheduled job in `routes/console.php` releases their stock reservation after 6 hours;
verified-but-unpaid bookings are released after 24.

### Payments

| Route | Purpose |
|---|---|
| `GET /register/{ref}/pay` | Summary and "Pay now" |
| `POST /register/{ref}/pay` | Creates the checkout link, redirects to Flutterwave |
| `GET /register/{ref}/payment/return` | Where Flutterwave sends the guest back |
| `POST /webhooks/flutterwave` | Server-to-server confirmation |

Three things make this safe:

1. **The redirect is never trusted.** It is a browser navigation. The return route
   re-verifies against Flutterwave using the `tx_ref` read from *our* database, not from
   the query string.
2. **Amount and currency are checked before fulfilment.** A transaction whose amount or
   currency does not match is held as `pending` for manual review rather than failed, so
   a genuine mismatch never silently issues tickets.
3. **Fulfilment is idempotent.** A row lock plus an existence check means a webhook, a
   redirect and a reconcile run landing together still issue exactly one set of tickets.

Webhook signature checking uses the `verif-hash` header compared with `hash_equals` —
that is the v3 scheme. (The HMAC-over-body scheme often quoted online belongs to v4.)

If a webhook never arrives, `php artisan payments:reconcile` re-checks pending payments
every ten minutes, and admins can mark a payment paid manually from the panel for cash or
bank transfers.

---

## Check-in

Two entry points, both in the admin panel:

- **`/admin/check-in-console`** — type or paste a reference; instant hit/duplicate feedback
- **`/admin/qr-scanner`** — camera scanning via the browser, with a live feed and undo

Duplicate scans are prevented with a row-level `lockForUpdate()`, so two staff members
racing on the same ticket cannot both succeed. Every attempt is written to `check_ins`.

### QR payload format

The QR encodes `{reference}.{hmac}` where the HMAC is keyed with the app encryption key.
The scanner verifies the signature *before* touching the database, so forged codes are
rejected cheaply. Malformed codes never reach the ticket table.

---

## Project layout

```
app/
├── Console/Commands/         SendEventReminders, ReconcilePayments
├── Enums/                   EventStatus, TicketStatus, RegistrationStatus,
│                            PaymentStatus, SmsStatus, UserRole
├── Filament/
│   ├── Pages/               CheckInConsole, QrScanner
│   ├── Resources/           Events (+ ticket-type relation manager), Tickets,
│   │                        Registrations, Payments, Users, SmsMessages
│   └── Widgets/             TicketingStats, UpcomingEventsChart, RecentCheckIns
├── Http/Controllers/
│   ├── Admin/ExportController  CSV exports
│   ├── Site/                  Public pages (events, registration, payment, tickets)
│   └── Webhooks/              Twilio + Flutterwave callbacks
├── Jobs/                    ProcessFlutterwaveWebhook
├── Models/
├── Notifications/           TicketIssued, EventReminder
└── Services/                ReferenceGenerator, SmsService, OtpService,
                             RegistrationService, CheckInService,
                             FlutterwaveService, PaymentService,
                             PaymentFulfilmentService
deploy/                      nginx, supervisor, cron, backup/restore, PHP ini
```

### Schema

| Table | Purpose |
|---|---|
| `users` | Admin/staff accounts with `role` and `is_active` |
| `events` | Event metadata, schedule, capacity, status |
| `ticket_types` | Per-event tiers: price, allocation, sale windows |
| `registrations` | A booking: registrant, quantity, verification and payment state |
| `tickets` | Issued tickets, each with its own reference and check-in state |
| `payments` | Gateway transactions, amounts, settlement and raw payloads |
| `otp_verifications` | Hashed codes, expiry and attempt counters |
| `sms_messages` | Every SMS attempt with delivery status and errors |
| `check_ins` | Append-only audit log of admissions |

---

## Tests

```bash
php artisan test
```

49 tests covering the registration/OTP flow, reference generation, QR signing and
tamper-rejection, check-in (including duplicate rejection and undo), the Twilio webhook,
payments (hold-until-paid, amount/currency mismatch, webhook idempotency, manual
override, admin-only exports) and every admin page.

> Tests run against SQLite. One class of bug only appears on MySQL/MariaDB — see
> `explicit_defaults_for_timestamp` in [`deploy/DEPLOYMENT.md`](deploy/DEPLOYMENT.md).

---

## Deployment

Full instructions live in [`deploy/DEPLOYMENT.md`](deploy/DEPLOYMENT.md).

The short version:

```bash
sudo bash deploy/deploy.sh              # first-time provisioning
bash deploy/deploy.sh --update          # subsequent deploys
```

That script installs and configures Nginx, PHP-FPM, MySQL, Composer, Supervisor (queue
workers + scheduler), cron, daily database/files backups with retention, and a restore
script.

---

## Configuration reference

| Variable | Default | Notes |
|---|---|---|
| `OTP_LENGTH` | `6` | Code length |
| `OTP_TTL_MINUTES` | `10` | Code lifetime |
| `OTP_MAX_ATTEMPTS` | `5` | Wrong-code attempts allowed |
| `OTP_RESEND_COOLDOWN_SECONDS` | `60` | Per-phone resend throttle |
| `TICKET_REFERENCE_PREFIX` | `EVT` | Ticket reference prefix |
| `FLW_SECRET_KEY` | — | Flutterwave secret key (`_TEST` prefix selects sandbox) |
| `FLW_SECRET_HASH` | — | Webhook secret from the dashboard |
| `FLW_AMOUNT_IN_MINOR_UNITS` | `false` | Whether `amount` is major or minor units |
| `FLW_SESSION_DURATION` | `30` | Checkout link lifetime, minutes |

A ticket type with `quantity_total = 0` is treated as **unlimited**.