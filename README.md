# Frozen

A self-service frozen food ordering and inventory management system with a public price list, online ordering, and a staff portal for managing items, templates, images, and users.

## Features

**Public**
- Price list with item images and stock availability
- Online order form with delivery info
- Product catalogue page
- Manual/cooking instructions popup per item (template-based)

**Staff Portal**
- Google OAuth login with approval gate and Cloudflare Turnstile
- Item management — add, update stock, buy/sell price
- Image management — upload and assign images to items
- Sales recording and restock history
- Order management — view and manage online orders
- Manual templates — create reusable content templates with `{{VARIABLE}}` substitution, link to items
- PDF export — generate Senarai Harga (BM) and Stock List (EN) as downloadable PDFs
- Location management — set current selling location with map link
- User management — approve, ban, and manage staff accounts
- Activity log

## Tech Stack

- **Backend:** PHP (no framework)
- **Database:** SQLite via PDO
- **Auth:** Google OAuth 2.0 (`league/oauth2-google`)
- **Bot protection:** Cloudflare Turnstile
- **PDF generation:** mPDF (`mpdf/mpdf`)
- **Email:** Microsoft Graph API (via Azure app)
- **Frontend:** Vanilla JS, plain CSS

## Project Structure

```
htdocs/frozen/       # Application files
config/frozen.db     # SQLite database
config/config_frozen.php  # App configuration (not committed)
library/vendor/      # Shared Composer dependencies
```

## Setup

1. Install dependencies:
   ```bash
   cd library
   composer install
   ```

2. Copy and configure:
   ```
   config/config_frozen.php
   config/refresh_token_frozen.txt
   ```

3. Point your web server to `htdocs/` and ensure `config/frozen.db` is writable.

4. Visit `/frozen/home.php` to access the public page, or `/frozen/` to log in as staff.

## Notes

- The first user to log in will need to be approved via the Users page by an existing approved user.
- `config_frozen.php` and `refresh_token_frozen.txt` contain secrets and should not be committed.
- Database migrations run automatically on first load via `db.php`.
