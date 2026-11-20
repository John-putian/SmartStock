# SmartStock review and fixes

Completed October 9, 2026, using the supplied ZIP. The original archive and installed application/database were not changed. Repairs are supplied in a separate release. Testing used a private MariaDB instance and a separate local application server; no email or real store transaction was created.

## Confirmed problems repaired

| Problem | Repair |
|---|---|
| Missing interface libraries | Included local Bootstrap, icon fonts, Chart.js and QR generator recovered from the installed SmartStock copy. |
| Opening POS consumed an invoice number | Invoice allocation now happens only during checkout. |
| Category/item reports ignored discounts | Net revenue uses recorded discount shares; legacy shares are allocated proportionally from sale headers. |
| Product edits changed historical category/cost reporting | Captured sale data is used; missing historical information is identified instead of invented. |
| Legacy/unmatched categories suggested misleading restocks | Those rows remain history only, without order suggestions. |
| History and exports silently truncated at 500 sales | Removed the cap for complete selected-period details. |
| Cart, archive, delete, deactivate and logout actions used GET links | Converted to POST forms with central CSRF protection. |
| Empty session/token could pass token comparison | Verification requires a nonempty session token and POST request. |
| Disabled accounts kept session access; changed roles stayed stale | Status and role are refreshed at access checks. |
| Last administrator could be disabled through some paths | Added deactivation and inactive-edit guards. |
| Inventory changes could partly update units, quantity and ledger | Inventory mutations are transactional and lock their parent product. |
| Simultaneous stock receipts could duplicate unit numbers | Parent locks serialize label allocation; parallel test passed. |
| Stock correction to zero failed; negative receipts became positive | Exact count accepts zero; negative receive quantities are rejected. |
| Archived products entered cart/scan flows | Blocked when adding/scanning/pruning the cart and during checkout. |
| Stale discount still displayed against a changed cart | Display checks fingerprint, applies zero discount, and explains recovery. |
| Simultaneous duplicate checkout returned already-sold error | Current locking read of submission key resolves both requests to one sale. |
| Price decisions could overwrite newer listed prices | Locked current-price validation rejects stale approvals; edit rules use current locked data. |
| Automatic SKUs could collide across simultaneous item creation | Final SKU derives from the inserted product ID. |
| Password hashes were embedded in browser edit data | Account lists expose only required edit/display fields. |
| User data could terminate inline scripts; usernames used innerHTML | Safe JSON across views and textContent for dynamic account titles. |
| CSV text could execute spreadsheet formulas | Suspicious leading characters on string cells are neutralized. |
| Mutation controls risked nested forms | Independent forms and explicit button/input form associations. |
| Fresh schema lacked current checkout columns | Includes the existing additive Phase 1 schema changes. |
| Sample invoice counter lagged behind seeded sales | Counter synchronized after history import; fresh checkout verified. |
| Documented source/database access restrictions were missing | Added Apache directory and file restrictions. |
| Setup helper could overwrite an existing database | Helper refuses existing database; setup documentation distinguishes upgrades. |
| Mobile checkout required scrolling through all catalogue items | Added checkout jump and return link. |
| Feedback vanished automatically; search Enter could submit twice | Feedback remains until dismissed; search timer clears on Enter. |
| Important controls lacked accessible names/focus | Added login/POS labels, navigation state and keyboard focus styling. |
| Receipt linked to modules unavailable to its viewer | New Sale for cashiers; Sales History for management. |
| Debug errors/demo passwords were publicly visible | Explicit environment flags required; session cookie protections added. |

## Verification evidence

- 101 automated assertions passed, zero failed, including all four roles, page rendering, role denial, CSRF, malformed scalar filters, invalid dates, four Excel export responses, checkout rollback, stock, discount, duplicate submission and historical report reconciliation.
- Two additional parallel-process checks passed: identical concurrent submissions return one sale, and concurrent receipts retain distinct unit labels and correct quantities.
- Fresh schema and sample seed imported, with zero catalogue/unit quantity mismatches; fresh-install checkout succeeded.
- All 69 PHP source files passed syntax checks.
- Browser verified item add/remove, unit barcode entry, discount withdrawal, payment and receipt generation with correct change: PHP 3,000 received for PHP 2,900 gives PHP 100.
- POS visually inspected at 1440×900 and 390×844. Mobile checkout jump worked; no horizontal document overflow observed on that screen.
- QR pages render and their dependency is packaged. Physical scanner and printer verification is still required.
- Excel responses were checked as successful ZIP workbook output; desktop Excel validation of every file was not performed.

Evidence: test-results.txt, concurrency-results.txt, fresh-install-results.txt, pos-desktop.png, pos-mobile.png.

## Remaining acceptance checks

This is a tested repair release, not a guarantee of perfection in every production or UI/UX scenario.

1. Test on a backup copy of the store's actual database before switching the live installation. Sample records cannot cover every real-world state.
2. Configure and verify SMTP delivery with the owner's credentials. It remains disabled; no real email was sent.
3. Test scanners, receipt/label printers, paper sizes, print previews and supported browsers on actual devices.
4. Broaden concurrent-user testing beyond the two targeted scenarios, including account changes, cancellations, competing edits and interrupted requests. Last-admin checks are not a complete concurrency-safe account invariant.
5. Large datasets require pagination, performance/load testing and retention planning. Complete long-range reports may be slower after removing the silent row cap.
6. Confirm the retained role policy with the owner: all signed-in roles may list inventory, only cashiers use POS, management approves money-off decisions, and administrators manage accounts.
7. Category snapshots store names; renamed categories become history-only unless still matched by name. Stable captured category IDs would improve continuity across category renames.
8. Missing historical category/cost cannot be reconstructed accurately. Agree how those gaps should be presented in business reports.
9. Verify HTTPS, non-default passwords, database privileges, Apache .htaccess enforcement, backups/restores and server-failure recovery. Account-wide login rate limiting and a password-reset flow were not added.
10. Broaden upload/storage-limit and input-fuzzing tests. Randomized photo names and content checks remain, but the performed filter tests do not prove every malformed input or file is handled.

## Applying the corrected release

Back up first. Extract SmartStock-fixed.zip and follow README.md inside SmartStock. Preserve the store's config/database.php and config/mail.php values. Do not import sample data over a live database. install.php remains disabled.

## October 9 policy update — manager-only discounts

Only active Store Manager accounts can approve or reject discount requests. Administrators retain read-only discount oversight and their existing price-change permissions. Request notifications go only to managers. Checkout independently verifies that the approver is still an active manager. An unused administrator approval is marked invalid in POS; the cashier must cancel it and request manager approval again. Historical completed receipts are preserved.

23 targeted checks passed, including manager approval and discounted checkout, administrator/staff/cashier model denials, administrator forged POST denial, correct UI buttons, manager-only notification routing, used-status rendering, and rejection of legacy administrator approvals. All PHP files passed syntax checks. Evidence: manager-discount-test-results.txt.
