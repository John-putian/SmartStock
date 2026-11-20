# Handing Smart Stock to A&A Household Appliances Trading

Written 9 October 2026. Work through part 1 before the handover date — those
items should not reach a client. Parts 2 and 3 are the handover itself.

---

## 1. Must be done before handover

### 1.1 The sales history in the system is not real

This is the most important item on the page.

`aa_inventory` holds **1,105 sales totalling about ₱5.2 million**, spread from
30 January to 2 October 2026 at a very even ~135 sales a month, recorded by
exactly two cashiers (559 and 546). The audit trail, which records what people
actually did, **only begins on 21 September 2026**, and the staff accounts' first
logins are recent. Nine months of trading cannot exist without nine months of
audit entries. That data was seeded for development.

Why it matters to the client:

- Every figure on the **Dashboard**, in **Reports**, in **Sales by Category** and
  in **Sales Analytics** is computed from it. Hand it over unchanged and the
  owner is reading fiction about her own shop.
- The **reorder forecasts** are driven by it.
- The **AI price suggestions** draw on it. When the system said *"₱5,510, based
  on 15 similar sales"*, those fifteen sales were invented. That number looks
  authoritative and is not.

Two honest options — this is a decision for you and the client, not something to
leave undecided:

| Option | What happens |
|---|---|
| **Start clean** (recommended for real use) | Keep products, categories, units, users and settings. Delete `sales`, `sale_items`, `stock_movements`, `discount_requests`, `price_requests`, `audit_logs`, and reset `invoice_counters`. The shop begins recording its own trading from day one, and every report is true from then on. |
| **Keep it as a demonstration** | Only if the client explicitly understands it is sample data. Then it is a training system, not a live one, and nobody should make buying decisions from its reports. |

Do not pick the middle path of saying nothing. A report that looks real and is
not is worse than no report.

### 1.2 Your personal accounts are inside the system

Two credentials in the files belong to **John Ray**, not to the shop:

- **`config/photo-ai.local.php`** — a Google Gemini API key on your personal
  Google account. Usage is attributed to you and billed to you. The shop would
  be spending your allowance, and you would be unable to see or control what it
  spends it on.
- **`config/mail.php`** — a Gmail app password for **junjunputian@gmail.com**.
  This is the serious one. An app password is full access to that mailbox: anyone
  who opens that file on the shop's laptop can read and send your personal email.
  It must not be handed over under any circumstances.

Before handover:

1. Revoke the Gemini key at **aistudio.google.com/apikey**, and the app password
   at **myaccount.google.com/apppasswords**. Both have also appeared in a chat
   transcript, which is a second reason to retire them.
2. Have the shop create its own Google account — something like
   `aasurplus.panabo@gmail.com` — and generate the key and app password there.
3. Put the shop's values in, and set the administrator's notification address to
   the shop's address rather than yours (**Email Notifications → Who is
   emailed**).

If the client does not want to set up a Google account, both features switch off
cleanly: blank the key and the rest of Smart Stock carries on, and set
`'enabled' => false` in `config/mail.php` so only the in-system bell is used.

### 1.3 The ₱500 demo price is still switched on

`config/photo-ai.php` has `'demo_pricing' => true`. When the AI drafts an item
the shop has never sold before, the system offers **₱500** as a placeholder. It
is labelled as a demo figure, but it has no business appearing at all once real
money is involved.

Set `'demo_pricing' => false` before handover. The system then says plainly that
it has no price suggestion and asks a person to set one, which is the correct
behaviour for a real shop.

### 1.4 Passwords and accounts

- Every account's password is known to the three of you. The owner, the manager,
  the staff member and the cashier must each set their own on day one, with
  nobody else watching.
- **`cashier2` is John Ray's own account.** It has 546 of the seeded sales
  against it. Either rename it for a real cashier or deactivate it — a developer
  should not hold a live till account in a client's shop.
- Decide who the real administrator is. Right now it is "Ana Marie Abella", and
  the administrator can approve mark-downs, manage users and read everything.

### 1.5 The database has no password

`config/database.php` connects as **`root` with an empty password**. That is the
XAMPP default and it is normal on a developer's laptop. On a shop machine holding
sales records it means anyone who reaches the database reaches everything.

Combined with the firewall finding from 9 October — **mysqld has an inbound
allow rule on the Public profile** — the database is exposed whenever that laptop
joins a public network such as a café or a mall. Two fixes, both worth doing:

1. Set a MySQL root password (XAMPP → Shell → `mysqladmin -u root password`),
   then put it in `config/database.php`.
2. In Windows Defender Firewall, restrict the **mysqld** and **Apache HTTP
   Server** inbound rules to the **Private** profile, and mark the shop Wi-Fi as
   Private. Smart Stock still works on the shop network; it stops answering on
   public ones.

### 1.6 Remove the second copy from the web folder

`C:\xampp\htdocs\SmartStock-reviewed` is still there and still served, at
`http://<address>/SmartStock-reviewed/`. It points at a **different database**
(`aa_inventory_review`). If anyone opens it by accident they will enter sales
that silently go nowhere.

Move it out of `htdocs` entirely — into Documents, or delete it. The code backup
at `Documents\SmartStock-code-backup-2026-10-09` is the copy worth keeping.

---

## 2. Set the client up to run it without you

### 2.1 Backups

There is a verified backup — `Documents\SmartStock-DB-Backups\aa_inventory-2026-10-09.sql`,
proven on 9 October by restoring it into a scratch database and confirming all 15
objects and 6,483 rows came back. And there is a double-click
**`Backup-SmartStock-Database.bat`** in that folder.

What the client needs:

- To be shown the .bat and told **run it at the end of every trading day**.
- An **off-machine copy**. Every backup is currently on the same laptop as the
  database, which protects against nothing — not theft, not a drive failure, not
  the ransomware that would encrypt both. A USB stick in the owner's drawer, or
  Google Drive, is enough.
- To know the restore command, which is in the runbook.

### 2.2 Teach the three things that break

1. **"MySQL shutdown unexpectedly"** on the first Start in XAMPP — click Start
   again. It is crash recovery finishing after the timeout, not a fault.
2. **The phone cannot connect** — open **Help → Open Smart Stock on a phone**.
   The address changes when the Wi-Fi changes, and that page always shows the
   current one.
3. **Photo suggestions unavailable** — the AI service is down or out of
   allowance. Type the details in by hand; nothing else is affected.

### 2.3 Hand over the documents

These ship inside the project folder:

- `HOW-TO-RUN.txt` — starting the system
- `ROLE-FLOWS.md` — who may do what, and the approval chains
- `DELIVERY-2026-10-09.md` — what was built and what was tested
- `REVIEW-AND-TESTS-2026-10-08.md` — the test evidence
- This file

The **Help** page inside the system is written for someone who has never seen it
before, and the permission table on it is generated from the router, so it cannot
drift out of date. Point the staff at it rather than at paper.

---

## 3. What to say honestly at the handover

Claiming more than is true is the fastest way to lose a client's trust the first
time something misbehaves. What can be said with evidence:

> The inventory, sales, approval and decision-support flows are verified against
> a real database — 252 automated checks covering permissions, till operations,
> invoice numbering under simultaneous use, the discount approval chain, and what
> staff see when the AI service fails. Email notifications were proved by sending
> a real one. The backup was proved by restoring it.

And what is still open, which the client should hear from you rather than
discover:

1. **No phone has yet loaded the system.** Everything needed is in place — the
   firewall already allows it, the address page works — but it has not been done
   on a handset even once. Do this before the meeting; it takes a minute.
2. **Phone layouts were tested at emulated phone size**, not on a real device.
3. **A real iPhone HEIC photo** has not been through the converter.
4. **No test with several staff on several tills at once.**
5. **Printing has not been tested on the shop's printer** — receipts or QR
   labels. Test this before handover; a till that cannot print is not a till.
6. **MariaDB crashed twice on 2 October.** A fix was applied and has held since,
   but that is not proof. The version in use (10.4) stopped receiving security
   updates in June 2024. Worth planning an upgrade.
7. The system runs on **one laptop**, on the shop's Wi-Fi, with no internet
   access. If that laptop is lost, the shop's records go with it unless the
   backups are off the machine.

---

## Quick checklist

- [ ] Decide and act on the seeded sales data (§1.1)
- [ ] Revoke both personal credentials; set up the shop's own (§1.2)
- [ ] `'demo_pricing' => false` (§1.3)
- [ ] Everyone sets their own password; deal with `cashier2` (§1.4)
- [ ] MySQL root password; firewall rules to Private only (§1.5)
- [ ] Move `SmartStock-reviewed` out of `htdocs` (§1.6)
- [ ] Off-machine backup copy, and show the client the .bat (§2.1)
- [ ] Load the system on a phone, once, for real
- [ ] Print one receipt and one QR label on the shop's printer
- [ ] Walk the client through Help, and leave the documents with them
