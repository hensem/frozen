# Changelog

## [2026-09-29]

### Added
- Dashboard: edit and delete existing locations via searchable dropdown — type to filter, select to populate fields, Save to update, Delete (disabled for active location)

## [2026-09-29 02:19]

### Added
- Temporary item flag (`is_temp`) — temp items are hidden from public senarai harga, PDF, and sell page
- Merge feature: merge a temp item into a sold-out item, transferring stock and updating price, restock history, and price history

## [2026-09-28 03:18]

### Added
- Item price history: new `price_history.php` page showing buy/sell price changes over time
- Price History button on each item row in Items page
- `item_price_history` table in DB to record price changes with timestamp and user
- `created_at` and `updated_at` columns on `items` table (existing rows remain null)
- Item images in Senarai Harga PDF — displayed inline in the Item column, size calculated dynamically to fit all items on one page
- User manual `MANUAL.md` in Malay

### Changed
- Senarai Harga PDF now has 2 columns (Item with image, Harga) instead of separate image column

## [2026-09-26 10:19]

### Changed
- Moved hardcoded protected admin email to `config_frozen.php` as `FROZEN_PROTECTED_EMAIL` constant

## [2026-09-26 06:34]

### Added
- Template images: add images to a template using `[[NAME]]` syntax, same flow as variables (add first, then use in content)
- Searchable image picker with thumbnail preview when adding an image to a template

## [2026-09-26 03:50]

### Added
- QR code page as the second page of the Senarai Harga PDF
