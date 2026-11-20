# Product entry UI update — 9 October 2026

## Staff flow

1. Open Add item on a phone or computer.
2. Take a product photo or choose one from the gallery. The photo is optional.
3. If photo suggestions are enabled, generate a draft and select the details to use. Review the name, category, condition and description against the actual piece.
4. Enter acquisition cost, selling price and quantity. The initial-price suggestion remains an explicit choice; unmatched history uses the labeled ₱500 demo placeholder.
5. Open More details only when brand, SKU, serial number or reorder level are needed. A blank SKU is generated when saving.
6. Save the product. On the product details screen, select Print labels for stock and attach a unique label to each physical piece.

## Changes

- One ordered form: Photo → Details → Price and stock → Save and QR labels.
- Compact photo section on desktop; stacked controls on smaller screens.
- Larger touch controls and a fixed Save button on phone-sized layouts.
- Optional details are collapsed, and expand when they contain an error.
- Errors are displayed beside fields, with links in the error summary.
- Invalid submissions keep entered details and photo/price drafts in the current page. Server validation errors keep the saved photo when editing; a new upload must be selected again, with an explicit reminder.
- Invalid photo uploads and malformed or fractional quantities are rejected before saving.
- Edit retries compare the proposed price against the original listed price for the approval hint.
- The Save button restores its label when returning through browser history.

Discount permissions remain manager-only. Existing item-price approval rules remain in place.

## Verification and remaining limits

All PHP files passed syntax checks. Changed JavaScript files passed syntax checks. Isolated validation and rendered-form checks passed for valid entry, invalid categories, invalid money, below-cost prices, fractional/overflow quantities, optional-field limits, upload failures, field retention, stored photos and the original edit-price baseline.

The revised layout was not visually verified in a browser. Local test connections timed out even after network access was granted; automatic approval review rejected an alternate local server launch. Database-backed saves and QR generation were not rerun for this UI update. Earlier test reports describe earlier versions and do not constitute new verification of this layout.

Physical Android/iPhone camera capture and printing still need acceptance testing. Live AI generation still requires a configured Gemini API key and testing with real store photos. This package is an updated source copy; it has not been installed over the store's running system or database.
