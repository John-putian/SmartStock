# Review, fixes and test results — 8 October 2026

Independent review of the photo-first release. Nothing in the installed system
(`C:\xampp\htdocs\SmartStock`) or the real `aa_inventory` database was touched.
All work was done on a separate copy, against a throwaway MariaDB 10.11 server
on its own port with its own data directory, loaded from `schema.sql` +
`seed.sql`, with the application served by PHP's built-in server.

---

## 1. What already worked

Verified by running it, not by reading the code. The release I was given is in
good shape; most of this section is confirmation rather than repair.

- **Photo-first entry** — Photo → Details → Price and stock → Save and QR, as
  four numbered steps on one page, on both a 1280px desktop and a 390px phone.
- **Take photo / Choose from gallery / manual file input**, all three present.
  "Take photo" sets `capture="environment"` (rear camera); "Choose from gallery"
  removes it. Both open the picker; the manual input always works.
- **Price suggestions** from the shop's own sales history, discount-adjusted,
  median of the closest name matches — falling back to a **labelled ₱500 demo
  placeholder** when nothing matches. The price field does **not** change until
  the user presses *Use suggested price*, and **acquisition cost is never
  touched**.
- **Photo suggestions** refuse cleanly when no AI key is configured. There is no
  code path that fabricates an AI answer.
- **Discount approval is enforced on the server** — the router refuses the
  cashier and work staff before any controller runs, and the rank is checked
  again in the model and a third time against the database at checkout.
- **Validation** returns errors beside each field, keeps what was typed, and
  explains that a photo must be chosen again.
- **CSRF** is now enforced centrally in the front controller for every mutating
  route, and the till was converted from links to POST forms to match.
- **SKU generated when blank**; brand, SKU, serial and reorder level are tucked
  under *More details*, collapsed by default.
- **One QR code per physical piece**, unique across the whole register.
- **Correctness under load** — two tills racing for the same piece produce
  exactly one sale; a double submission produces one sale; stored quantity never
  drifts from the counted pieces.

## 2. What I fixed

| # | Problem found | Fix |
|---|---|---|
| 1 | The till was left out of the phone work. The scan box was **38px** tall and its placeholder was cut off mid-word, with *Add piece* squeezed beside it. | Till controls now 46px with 16px text (which also stops iOS zooming on focus); below 576px the scan field takes the full width and *Add piece* moves to its own 48px row. The decorative barcode glyph steps aside at that width. |
| 2 | **Requirement 11 was not met**: nothing in the app distinguished photographing a product from scanning a QR label, and the scanner's real behaviour was undocumented. | Added *How the scanner works here* at the till, a line on the Add item photo step, and a new Help section contrasting the two, including piece code vs item SKU. |
| 3 | The AI model default was `gemini-3.8-flash`, which is not a model name Google publishes. | Default is now `gemini-3.5-flash`, still overridable with `SMARTSTOCK_AI_MODEL`. |
| 4 | The request sent no `Api-Revision` header, so a later change to the service could silently alter the response shape the parser reads. | Pinned to `2026-05-20` (configurable), matching Google's published examples. |
| 5 | Discount approval was locked to the Store Manager role alone, which shut the **owner** out of authorising a mark-down on her own goods. Worse, it was inconsistent: had the rule been relaxed in one place only, an owner-approved discount would have been accepted at the approvals screen and then **refused at checkout**, because the till re-checks the approver's rank against the database. | The administrator and the Store Manager may both approve. Changed in all four layers that enforce it — router, model, the staleness re-check, and the checkout verification — plus the five places that described the rule in words. The cashier and work staff are still refused. |

Files changed: `app/views/pos/index.php`, `app/views/inventory/form.php`,
`app/views/help/index.php`, `app/views/approvals/index.php`,
`app/views/dashboard/floor.php`, `app/controllers/HelpController.php`,
`app/core/Auth.php`, `app/core/PhotoAssistant.php`, `app/models/Sale.php`,
`app/models/DiscountRequest.php`, `assets/css/style.css`, `config/photo-ai.php`,
`index.php`. Nothing else was altered.

## 3. Test results

190 automated checks, all passing, run against the isolated server and database.

| Suite | Checks | Result |
|---|---|---|
| Sign-in, role permissions, product save, validation, suggestion endpoints | 42 | all passed |
| Sales, discount chain, stale approvals, duplicate submission, owner approval end to end | 36 | all passed |
| Concurrency, price approvals, scanner lookup, label uniqueness | 19 | all passed |
| Scanner/photo guidance, AI configuration, credential scan | 17 | all passed |
| Browser UI at 1280×900 and 390×844 | 62 | all passed |
| Camera and gallery wiring at both sizes | 14 | all passed |

Highlights worth naming:

- **Permission matrix** — 18 role/route combinations checked against the server's
  own answer (HTTP 403 + redirect), not against which buttons are visible.
- **Discount approval** — the cashier and work staff are each refused by the
  server with a forged POST. The manager and the administrator each succeed, and
  in both cases the sale completes, the approval is spent exactly once, tied to
  that sale, and its shares add up to the discount to the centavo.
- **Stale approval** — changing the cart after approval blocks the sale, and the
  approval is not consumed.
- **Race** — two tills holding the same piece, checked out simultaneously
  through six server workers: one sale, one sold piece, no drift.
- **Phone layout** — no horizontal scrolling anywhere tested, every primary
  control ≥44px, Save reachable at all times via the sticky bar.

## 4. What is NOT tested, and why

Say these plainly rather than claiming the system is finished.

- **The AI photo feature has never run against the real service.** No API key was
  available, so every test exercised the *unconfigured* path. The request shape
  was checked against Google's published Interactions API reference (endpoint,
  `steps`/`model_output`/`text` response shape, inline image `mime_type`+`data`,
  `system_instruction`, `response_format`), and the model name and revision were
  corrected to published values — but **until someone configures a key and runs
  it, treat this feature as unproven.**
- **No real phone or real camera.** Testing used Chromium at 390×844 with touch
  emulation. That proves layout, touch sizes and that the buttons request the
  rear camera; it does not prove behaviour on a specific Android or iOS browser.
  HEIC photos from an iPhone still need exporting as JPG.
- **No hardware barcode scanner.** The scan path was tested by submitting codes
  the way a scanner types them. A physical scanner should still be tried once.
- **MariaDB 10.11, not the shop's 10.4.** The SQL used is well within both.
- **Printing was not exercised** — label sheets and receipts were rendered and
  read, but not sent to a printer.
- **Email was deliberately left off.** No message was sent.

## 5. Before this goes live

1. Back up `aa_inventory` first. The schema in this release differs from the
   installed one; compare before importing anything.
2. **The discount rule, as it now stands.** The **administrator (the owner) and
   the Store Manager** may each approve a POS discount; the cashier and work
   staff may not. The manager can still do it alone — the owner does not need to
   be there. To make it manager-only instead, change
   `Auth::canApproveDiscount()` to `self::isManager()` **and** narrow the two
   `role IN ("admin","manager")` checks in `Sale::checkout()` and
   `DiscountRequest::approvedFor()`; changing only the first silently breaks
   checkout. Product *price* approvals are unchanged.
3. To enable photo suggestions, set `SMARTSTOCK_GEMINI_API_KEY` on the server (or
   fill `config/photo-ai.php`, which `config/.htaccess` keeps off the web). **Do
   not commit a key, and do not put it in a browser script.** Set
   `SMARTSTOCK_DEMO_PRICING=0` to switch off the ₱500 placeholder for real
   trading.
4. Try one real phone, one real camera photo and one hardware scanner before the
   defence.
