#!/usr/bin/env python3
"""Generates database/seed.sql : realistic sample data for the
A&A Household Appliances Trading inventory system."""
import random, subprocess, datetime as dt

random.seed(307)
TODAY = dt.date(2026, 9, 6)
START = dt.date(2026, 1, 5)


def q(s):
    if s is None:
        return "NULL"
    return "'" + str(s).replace("\\", "\\\\").replace("'", "''") + "'"


def rel(ts):
    """Emit a datetime relative to the import date, so the sample history
    always ends 'today' no matter when the SQL file is imported."""
    days = (TODAY - ts.date()).days
    t = ts.strftime("%H:%M:%S")
    if days == 0:
        return f"TIMESTAMP(CURDATE(),'{t}')"
    return f"TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL {days} DAY),'{t}')"


def php_hash(pw):
    out = subprocess.check_output(
        ["php", "-r", f"echo password_hash({q(pw)}, PASSWORD_DEFAULT);"])
    return out.decode().strip()


# ---------------------------------------------------------------- users
# The last field is where the end-of-day takings email is sent. Only the
# office is ever emailed, and a blank address means that account gets the
# bell alone.
users = [
    ("Ana Marie Abella",   "admin",   "admin123",   "admin",   "0917-555-0101", "carl.duque@jmc.edu.ph"),
    ("Carl Ulrick Duque",  "manager", "manager123", "manager", "0917-555-0102", ""),
    ("Rossen Dale Dizon",  "cashier", "cashier123", "cashier", "0917-555-0103", ""),
    ("John Ray Puti-an",   "cashier2", "cashier123", "cashier", "0917-555-0104", ""),
    ("Rico Andrade Selma", "staff",   "staff123",   "staff",   "0917-555-0105", ""),
]

# Only these two may authorise a discount or a price reduction.
APPROVERS = [1, 2]

# ----------------------------------------------------------- categories
CAT_CODES = ["APP", "KIT", "FUR", "KWD", "DEC", "ELE", "ACC", "MUS", "SPT"]

categories = [
    ("Major Appliances",     "Refrigerators, washing machines, air conditioners and other large units"),
    ("Kitchen Appliances",   "Rice cookers, microwave ovens, blenders and small kitchen electrics"),
    ("Furniture",            "Tables, chairs, cabinets, desks, drawers and storage furniture"),
    ("Kitchenware & Dining",  "Plates, cups, cookware, glassware and serving pieces"),
    ("Home Decor",           "Vases, frames, ornaments and decorative pieces"),
    ("Electronics & Audio",  "Televisions, speakers, radios and audio-video components"),
    ("Home Accessories",     "Fans, irons, vacuum cleaners and general household accessories"),
    ("Musical Instruments",  "Guitars, keyboards and other second-hand instruments"),
    ("Sports & Leisure",     "Rackets, sporting goods and outdoor leisure items"),
]

# ------------------------------------------------------------- products
# The real A&A Surplus stock, identified from the store's own photographs.
# Prices are set against observed second-hand Japan-surplus market rates in
# the Philippines (used.com.ph / Carousell PH, Sept 2026).
# (name, category index, brand, cost, price, reorder, popularity, condition, image slug)
P = [
    # ---- Furniture (cat 3) -------------------------------------------------
    ("Fabric Tub Accent Chair", 2, None, 1500, 2800, 1, 4, "Class B", "tub-accent-chair"),
    ("Wooden Dining Table Set (4 Seats)", 2, None, 4600, 8500, 1, 3, "Class A", "wooden-dining-table-set"),
    ("Steel Filing Cabinet 8-Drawer", 2, None, 1700, 3200, 1, 3, "Class B", "steel-filing-cabinet"),
    ("Glass-Door Wooden Display Cabinet", 2, None, 3500, 6500, 1, 2, "Class A", "glass-door-display-cabinet"),
    ("Reclining Lounge Chair", 2, None, 1900, 3500, 1, 3, "Class B", "reclining-lounge-chair"),
    ("Wooden Chest of Drawers", 2, None, 2300, 4200, 1, 3, "Class B", "wooden-chest-of-drawers"),
    ("Wooden Study Desk with Shelf", 2, None, 1500, 2900, 2, 4, "Class B", "wooden-study-desk"),
    ("Executive Office Chair (Leather)", 2, None, 2000, 3800, 1, 4, "Class A", "executive-office-chair"),
    ("Steel Single-Door Locker", 2, None, 1400, 2600, 2, 3, "Class B", "steel-single-door-locker"),
    ("Metal-Frame Writing Desk", 2, None, 1300, 2400, 2, 3, "Class B", "metal-frame-writing-desk"),
    ("Oval Centre Table with Stone Top", 2, None, 2400, 4500, 1, 2, "Class A", "oval-centre-table"),
    ("Carved Wooden Shelf Rack", 2, None, 1100, 2200, 2, 3, "Class B", "carved-shelf-rack"),
    ("Wooden Filing Chest 5-Drawer", 2, None, 2100, 3900, 1, 3, "Class A", "wooden-filing-chest"),
    ("Cream Writing Desk with Drawer", 2, None, 1400, 2700, 2, 3, "Class B", "cream-writing-desk"),
    ("Wooden Sideboard 6-Drawer", 2, None, 2800, 5200, 1, 3, "Class A", "wooden-sideboard-6drawer"),
    ("White Chest of Drawers 6-Drawer", 2, None, 3100, 5800, 1, 3, "Class A", "white-chest-of-drawers"),
    ("Louvre-Door Wooden Wardrobe", 2, None, 3300, 6200, 1, 2, "Class A", "louvre-door-wardrobe"),
    ("Utility Dining Table with Shelf", 2, None, 1300, 2500, 2, 3, "Class B", "utility-dining-table"),
    ("Folding Sun Lounger", 2, None, 900, 1800, 2, 3, "Class B", "folding-sun-lounger"),
    ("Wooden Armchair, Upholstered", 2, None, 1400, 2600, 2, 3, "Class B", "wooden-armchair-upholstered"),
    ("Cream Console Table 2-Drawer", 2, None, 1800, 3400, 1, 3, "Class A", "cream-console-table"),
    ("Black TV Stand / Media Cabinet", 2, None, 1500, 2900, 2, 4, "Class B", "black-tv-stand"),
    ("Swivel Office Chair (Gas Lift)", 2, None, 1000, 1900, 3, 5, "Class B", "swivel-office-chair"),
    ("Drop-Leaf Table with Drawer", 2, None, 1700, 3200, 1, 3, "Class A", "drop-leaf-table"),

    # ---- Kitchenware & Dining (cat 4) --------------------------------------
    ("Ceramic Dinner Plate Set", 3, "Noritake", 280, 650, 4, 5, "Class A", "ceramic-dinner-plate-set"),
    ("Gold-Rim Teacup & Saucer Set", 3, None, 380, 850, 3, 4, "Class A", "gold-rim-teacup-saucer-set"),
    ("Green-Rim Dessert Plate Set (5 pcs)", 3, None, 250, 580, 4, 4, "Class A", "green-rim-dessert-plate-set"),

    # ---- Home Decor (cat 5) ------------------------------------------------
    ("Cloisonne Vase, Tiger Design", 4, None, 1200, 2400, 1, 2, "Class A", "cloisonne-tiger-vase"),
    ("Japanese Ceramic Sake Jar", 4, None, 700, 1500, 2, 2, "Class B", "japanese-sake-jar"),
    ("Framed Art Print, Ornate Gold Frame", 4, None, 900, 1800, 2, 3, "Class A", "framed-art-gold-frame"),

    # ---- Musical Instruments (cat 8) ---------------------------------------
    ("Classical Acoustic Guitar", 7, None, 1100, 2200, 1, 3, "Class B", "classical-guitar"),

    # ---- Sports & Leisure (cat 9) ------------------------------------------
    ("Tennis Racket Set with Cases", 8, "Spalding", 550, 1200, 2, 3, "Class C", "tennis-racket-set"),
]

CONDITIONS = ["Class A", "Class B", "Class B", "Class C", "For Parts"]
CONDITION_W = [30, 35, 20, 12, 3]

ONE_OFF = {2}          # Furniture - every piece is unique surplus

products = []
for i, (name, cat, brand, cost, price, reorder, pop, cond, slug) in enumerate(P, start=1):
    if cat in ONE_OFF:
        # A unique piece can only ever be 1 or 0, so a reorder level is
        # meaningless - the alert should fire only when it is actually gone.
        reorder = 0
    sku = f"AA-{CAT_CODES[cat]}-{i:04d}"
    serial = f"JP{random.randint(100000,999999)}" if cat in (0, 1, 5, 6) else None
    products.append(dict(id=i, sku=sku, serial=serial, name=name, cat=cat + 1,
                         brand=brand, cost=cost, price=price, reorder=reorder,
                         pop=pop, cond=cond, img=f"items/{slug}.jpg",
                         one_off=cat in ONE_OFF))

DESCR = ("Japan surplus item, inspected by the store before release. Sold "
         "as-is; being second-hand, each unit is unique and the photograph "
         "shows the actual piece on the floor.")

# ------------------------------------------------- simulate 8 months ops
stock = {}
movements = []      # (product_id, type, qty, balance, ref, notes, user_id, datetime)
sales_rows = []     # dict
item_rows = []      # dict
audit_rows = []

# opening stock (deliveries arrive in batches - typical for surplus retail)
for p in products:
    if p["one_off"]:
        opening = 1                     # exactly one piece of this design
    else:
        opening = max(1, int(p["pop"] * random.uniform(0.8, 1.8)))
        if p["price"] >= 4000:
            opening = max(1, opening // 2)
    stock[p["id"]] = opening
    movements.append((p["id"], "in", opening, opening, "OPENING",
                      "Opening stock / initial container batch", 2,
                      dt.datetime.combine(START, dt.time(8, 0))))

invoice_seq = 0
sale_seq = 0
cashiers = [3, 4]
CUSTOMERS = [None, None, None, "Walk-in Customer", "Maria Santos", "Jose Ramirez",
             "Liza Fernandez", "Danilo Cruz", "Grace Villanueva", "Roberto Yap",
             "Nenita Alcantara", "Peter Lim", "Rosalie Manalo"]

day = START
while day <= TODAY:
    dow = day.weekday()
    # weekend / payday uplift typical of provincial retail
    base = 3 if dow < 5 else 6
    if day.day in (15, 16, 30, 31, 1):
        base += 3
    # december-like seasonal ramp not applicable (Jan-Sep) - light growth trend
    growth = 1.0 + (day - START).days / 900.0
    txn_count = max(1, int(random.gauss(base * growth, 2)))

    for _ in range(txn_count):
        # pick 1-3 distinct products weighted by popularity
        avail = [p for p in products if stock[p["id"]] > 0]
        if not avail:
            break
        k = random.choices([1, 1, 1, 2, 2, 3], k=1)[0]
        weights = [p["pop"] ** 2 for p in avail]
        picked, seen = [], set()
        for _ in range(k * 3):
            if len(picked) >= k:
                break
            c = random.choices(avail, weights=weights, k=1)[0]
            if c["id"] in seen:
                continue
            seen.add(c["id"])
            picked.append(c)
        if not picked:
            continue

        sale_seq += 1
        invoice_seq += 1
        hour = random.choices(range(8, 19), weights=[3, 5, 7, 8, 6, 5, 8, 9, 8, 6, 3])[0]
        ts = dt.datetime.combine(day, dt.time(hour, random.randint(0, 59), random.randint(0, 59)))
        cashier = random.choice(cashiers)
        subtotal = 0.0
        lines = []
        for p in picked:
            maxq = 1 if p["one_off"] else min(stock[p["id"]], 3 if p["price"] < 1500 else 1)
            if maxq < 1:
                continue
            qty = random.randint(1, maxq)
            line = qty * p["price"]
            subtotal += line
            stock[p["id"]] -= qty
            lines.append((p, qty, line))
            movements.append((p["id"], "out", qty, stock[p["id"]],
                              f"INV-{ts.year}{invoice_seq:05d}", "POS sale deduction",
                              cashier, ts))
        if not lines:
            sale_seq -= 1
            invoice_seq -= 1
            continue

        discount = 0.0
        if subtotal > 5000 and random.random() < 0.35:
            discount = round(subtotal * random.choice([0.03, 0.05]), 2)
        elif random.random() < 0.10:
            discount = float(random.choice([20, 50, 100]))
        total = round(subtotal - discount, 2)
        paid = float(int(total / 100) * 100 + 100) if total % 100 else total
        if random.random() < 0.25:
            paid = round(total, 2)
        change = round(paid - total, 2)
        pay = random.choices(["Cash", "Installment", "Bank Transfer"],
                             weights=[85, 9, 6], k=1)[0]
        inv = f"INV-{ts.year}{invoice_seq:05d}"
        # Every discount carries the administrator or manager who signed it off,
        # exactly as the till now demands of a live sale.
        approver = random.choice(APPROVERS) if discount > 0 else None
        sales_rows.append(dict(id=sale_seq, inv=inv, user=cashier,
                               cust=random.choice(CUSTOMERS),
                               sub=round(subtotal, 2), disc=discount, total=total,
                               paid=paid, change=change, pay=pay,
                               approver=approver, ts=ts))
        for p, qty, line in lines:
            item_rows.append(dict(sale=sale_seq, pid=p["id"], name=p["name"],
                                  sku=p["sku"], price=p["price"], qty=qty,
                                  line=round(line, 2)))

    # restocking: surplus containers arrive roughly twice a month
    if day.day in (3, 11, 18, 26):
        for p in products:
            if p["one_off"]:
                continue                # handled daily, see below
            if stock[p["id"]] > p["reorder"] + 1 or random.random() >= 0.92:
                continue                # still has plenty, or nothing suitable came

            add = max(2, int(p["pop"] * random.uniform(1.2, 2.4)))
            if p["price"] >= 4000:
                add = max(1, add // 2)

            stock[p["id"]] += add
            ts = dt.datetime.combine(day, dt.time(9, random.randint(0, 40)))
            movements.append((p["id"], "in", add, stock[p["id"]],
                              f"DR-{day.strftime('%y%m%d')}",
                              "Container delivery / restock", 2, ts))
    # A sold one-off design is replaced whenever a container happens to bring
    # a comparable piece - typically within a few days, never on request.
    for p in products:
        if p["one_off"] and stock[p["id"]] == 0 and random.random() < 0.28:
            stock[p["id"]] = 1
            movements.append((p["id"], "in", 1, 1, f"DR-{day.strftime('%y%m%d')}",
                              "Container delivery - one comparable piece", 2,
                              dt.datetime.combine(day, dt.time(9, random.randint(0, 40)))))

    day += dt.timedelta(days=1)

# every one-off design ends with exactly one piece on the floor
for p in products:
    if p["one_off"] and stock[p["id"]] != 1:
        diff = 1 - stock[p["id"]]
        stock[p["id"]] = 1
        movements.append((p["id"], "adjust", abs(diff), 1, "ADJ-COUNT",
                          "Physical count - one unique piece on the floor", 2,
                          dt.datetime.combine(TODAY - dt.timedelta(days=1), dt.time(17, 30))))

# a demo mix of alert states among the multi-piece lines
for pid, val in [(25, 2), (27, 3), (30, 1), (32, 1)]:
    if stock[pid] != val:
        diff = val - stock[pid]
        stock[pid] = val
        movements.append((pid, "adjust", abs(diff), val, "ADJ-0906",
                          "Physical count adjustment", 2,
                          dt.datetime.combine(TODAY - dt.timedelta(days=1), dt.time(17, 30))))


# ----------------------------------------------------------------- write
out = []
w = out.append
w("-- =====================================================================")
w("--  SAMPLE DATA - A&A Household Appliances Trading")
w("--  Generated for IT 307 defense / demonstration purposes.")
w("--  Sales history: %s to %s  (%d transactions)" % (START, TODAY, len(sales_rows)))
w("-- =====================================================================")
w("USE `aa_inventory`;")
w("SET FOREIGN_KEY_CHECKS = 0;")
for t in ["notifications", "price_requests", "sale_items", "sales",
          "stock_movements", "product_units", "audit_logs", "products",
          "categories", "users"]:
    w(f"TRUNCATE TABLE `{t}`;")
w("SET FOREIGN_KEY_CHECKS = 1;")
w("")

w("-- Users (admin123 / manager123 / cashier123 / staff123) ---------------")
vals = []
for i, (fn, un, pw, role, ct, em) in enumerate(users, start=1):
    vals.append(f"({i},{q(fn)},{q(un)},{q(php_hash(pw))},{q(role)},{q(ct)},"
                f"{q(em) if em else 'NULL'},'active')")
w("INSERT INTO `users` (`id`,`full_name`,`username`,`password_hash`,`role`,"
  "`contact_no`,`email`,`status`) VALUES")
w(",\n".join(vals) + ";")
w("")

w("-- Categories ---------------------------------------------------------")
vals = [f"({i},{q(CAT_CODES[i-1])},{q(n)},{q(d)})" for i, (n, d) in enumerate(categories, start=1)]
w("INSERT INTO `categories` (`id`,`code`,`name`,`description`) VALUES")
w(",\n".join(vals) + ";")
w("")

w("-- Products -----------------------------------------------------------")
vals = []
for p in products:
    vals.append("({id},{sku},{ser},{nm},{cat},{br},{ds},{cd},{cost},{price},{qty},{img},{re})".format(
        id=p["id"], sku=q(p["sku"]), ser=q(p["serial"]), nm=q(p["name"]),
        cat=p["cat"], br=q(p["brand"]), ds=q(DESCR), cd=q(p["cond"]),
        cost=p["cost"], price=p["price"], qty=stock[p["id"]],
        img=q(p["img"]), re=p["reorder"]))
w("INSERT INTO `products` (`id`,`sku`,`serial_no`,`name`,`category_id`,`brand`,`description`,`condition_grade`,`cost_price`,`selling_price`,`quantity`,`image_path`,`reorder_level`) VALUES")
w(",\n".join(vals) + ";")
w("")

w("-- Sales --------------------------------------------------------------")
CH = 400
for s in range(0, len(sales_rows), CH):
    chunk = sales_rows[s:s + CH]
    vals = ["({id},{inv},{u},{c},{sub},{d},{t},{p},{ch},{pm},{ap},{ts})".format(
        id=r["id"], inv=q(r["inv"]), u=r["user"], c=q(r["cust"]), sub=r["sub"],
        d=r["disc"], t=r["total"], p=r["paid"], ch=r["change"], pm=q(r["pay"]),
        ap=(r["approver"] if r["approver"] else "NULL"),
        ts=rel(r["ts"])) for r in chunk]
    w("INSERT INTO `sales` (`id`,`invoice_no`,`user_id`,`customer_name`,`subtotal`,`discount`,`total`,`amount_paid`,`change_due`,`payment_method`,`discount_approved_by`,`sale_date`) VALUES")
    w(",\n".join(vals) + ";")

w("")
w("-- Sale items ---------------------------------------------------------")
for s in range(0, len(item_rows), CH):
    chunk = item_rows[s:s + CH]
    vals = ["({sa},{pid},{nm},{sku},{pr},{qt},{ln})".format(
        sa=r["sale"], pid=r["pid"], nm=q(r["name"]), sku=q(r["sku"]),
        pr=r["price"], qt=r["qty"], ln=r["line"]) for r in chunk]
    w("INSERT INTO `sale_items` (`sale_id`,`product_id`,`product_name`,`sku`,`unit_price`,`quantity`,`line_total`) VALUES")
    w(",\n".join(vals) + ";")

w("")
w("-- Stock movement ledger ----------------------------------------------")
movements.sort(key=lambda m: m[7])
for s in range(0, len(movements), CH):
    chunk = movements[s:s + CH]
    vals = ["({pid},{ty},{qt},{bal},{ref},{nt},{u},{ts})".format(
        pid=m[0], ty=q(m[1]), qt=m[2], bal=m[3], ref=q(m[4]), nt=q(m[5]),
        u=m[6], ts=rel(m[7])) for m in chunk]
    w("INSERT INTO `stock_movements` (`product_id`,`type`,`quantity`,`balance`,`reference`,`notes`,`user_id`,`created_at`) VALUES")
    w(",\n".join(vals) + ";")

w("")
w("-- Individual physical units (one barcode per piece) -------------------")
unit_rows = []
uid = 0
for p in products:
    code_prefix = p["sku"]          # unit codes always extend the item SKU
    for n in range(1, stock[p["id"]] + 1):
        uid += 1
        # each surplus piece is graded on its own merits
        cond = random.choices(
            [p["cond"], "Class A", "Class B", "Class C"], weights=[62, 12, 16, 10], k=1)[0]
        note = random.choice([
            None, None, None,
            "Light surface scratches", "Minor scuff on one edge",
            "Small dent, does not affect use", "Very good condition for its age",
            "Slight fading from sunlight", "Hardware complete and working",
        ])
        recv = dt.datetime.combine(
            TODAY - dt.timedelta(days=random.randint(1, 120)),
            dt.time(random.randint(8, 16), random.randint(0, 59)))
        unit_rows.append("({pid},{code},{n},{cond},{note},'in_stock',{recv})".format(
            pid=p["id"], code=q(f"{code_prefix}-{n:02d}"), n=n,
            cond=q(cond), note=q(note), recv=rel(recv)))

for i in range(0, len(unit_rows), CH):
    w("INSERT INTO `product_units` (`product_id`,`unit_code`,`unit_no`,`condition_grade`,`notes`,`status`,`received_at`) VALUES")
    w(",\n".join(unit_rows[i:i + CH]) + ";")
print("units:", len(unit_rows))

w("")

w("")
w("-- Price reduction requests -------------------------------------------")
# A small, believable queue for the approvals screen: two waiting on a
# decision, one already approved and applied, one turned down.
pr_pool = [p for p in products if stock[p["id"]] > 0][:12]
random.shuffle(pr_pool)
pr_rows = []
pr_specs = [
    ("pending",  0.85, "Chipped veneer on the left side - customer asked for a lower price.", None, None),
    ("pending",  0.80, "Same model has been sitting on the floor for six weeks.", None, None),
    ("approved", 1.00, "Scratched top panel noticed while cleaning.", 2,
     "Checked the piece myself, the damage is real. Approved."),
    ("rejected", 0.75, "Walk-in buyer offered this much for cash today.", 1,
     "Too far below cost. Hold the listed price."),
]
for n, (status, factor, reason, judge, note) in enumerate(pr_specs):
    if n >= len(pr_pool):
        break
    p = pr_pool[n]
    price = float(p["price"])
    if status == "approved":
        # Already applied: the item now carries the reduced price.
        old_p, new_p = round(price * 1.18, 2), price
    else:
        old_p, new_p = price, round(price * factor, 2)
    made = TODAY - dt.timedelta(days=random.randint(1, 9))
    made_ts = dt.datetime.combine(made, dt.time(random.randint(9, 17), random.randint(0, 59)))
    done_ts = made_ts + dt.timedelta(hours=random.randint(2, 30))
    pr_rows.append(
        "({pid},5,{op},{np},{rs},{st},{jd},{nt},{dt},{ct})".format(
            pid=p["id"], op=old_p, np=new_p, rs=q(reason), st=q(status),
            jd=(judge if judge else "NULL"), nt=(q(note) if note else "NULL"),
            dt=(rel(done_ts) if judge else "NULL"), ct=rel(made_ts)))
if pr_rows:
    w("INSERT INTO `price_requests` (`product_id`,`requested_by`,`old_price`,`new_price`,"
      "`reason`,`status`,`decided_by`,`decision_note`,`decided_at`,`created_at`) VALUES")
    w(",\n".join(pr_rows) + ";")
w("")

w("-- Notifications ------------------------------------------------------")
# The real peso sign, not the HTML entity: a notice is stored as plain text and
# escaped on the way out, so an entity would be shown literally on screen.
PESO = "₱"
# Addressed to the office (admin and manager). Stock warnings are derived from
# the stock the seed actually ends with, so the bell agrees with the shelves.
notif = []


class Raw(str):
    """A value already written as SQL - emitted as-is instead of quoted."""


def sql(v, limit=None):
    return str(v) if isinstance(v, Raw) else q(v[:limit] if limit else v)


def notify(uid, ntype, sev, title, body, page, params, dedupe, when):
    notif.append(
        "({u},{t},{s},{ti},{b},{p},{pa},{d},{r})".format(
            u=uid, t=q(ntype), s=q(sev), ti=sql(title, 140), b=sql(body, 250),
            p=q(page), pa=(q(params) if params else "NULL"),
            d=sql(dedupe), r=rel(when)))


now = dt.datetime.combine(TODAY, dt.time(8, 5))

for office in APPROVERS:                       # admin and manager
    # Out of stock, then low stock, newest first.
    out_items = [p for p in products if stock[p["id"]] == 0][:2]
    low_items = [p for p in products
                 if 0 < stock[p["id"]] <= p["reorder"]][:3]

    for n, p in enumerate(out_items):
        notify(office, "stock_out", "danger",
               "Sold out: " + p["name"],
               "{} has no pieces left on the floor. Worth raising at the next "
               "container meeting.".format(p["sku"]),
               "inventory.view", "id=" + str(p["id"]),
               "stock_out:" + str(p["id"]),
               now - dt.timedelta(hours=2 + n))

    for n, p in enumerate(low_items):
        notify(office, "stock_low", "warning",
               "Running low: " + p["name"],
               "{} is down to {} piece(s), at or below its reorder level of {}."
               .format(p["sku"], stock[p["id"]], p["reorder"]),
               "inventory.view", "id=" + str(p["id"]),
               "stock_low:" + str(p["id"]),
               now - dt.timedelta(hours=5 + n))

    # The two price requests left pending above.
    for n, (status, factor, reason, judge, note) in enumerate(pr_specs[:2]):
        if n >= len(pr_pool):
            break
        p = pr_pool[n]
        # Same wording the live trigger writes, so a seeded notice and a notice
        # raised during the demo read identically.
        old_p = float(p["price"])
        new_p = round(old_p * factor, 2)
        notify(office, "price_request", "warning",
               "Price approval needed: " + p["name"],
               "Rico Andrade Selma asked to lower the price from "
               "{}{:,.2f} to {}{:,.2f}. The listed price has not moved."
               .format(PESO, old_p, PESO, new_p),
               "approvals", "status=pending",
               "price_request:" + str(n + 1),
               now - dt.timedelta(days=1, hours=n))

    # Yesterday's takings.
    y = TODAY - dt.timedelta(days=1)
    y_sales = [r for r in sales_rows if r["ts"].date() == y]
    if y_sales:
        rev = round(sum(r["total"] for r in y_sales), 2)
        pcs = sum(l["qty"] for l in item_rows
                  if any(s["id"] == l["sale"] and s["ts"].date() == y for s in y_sales))
        # The date has to be worked out at import time, not here. Every other
        # row is placed relative to CURDATE(), so a weekday spelled out now
        # would disagree with the timestamp beside it the moment the file is
        # imported on a different day. The dedupe key is built the same way the
        # live digest builds it, so the app sees yesterday as already reported.
        notify(office, "daily_income", "success",
               Raw("CONCAT('Takings for ', DATE_FORMAT("
                   "DATE_SUB(CURDATE(), INTERVAL 1 DAY), '%W, %M %e'), "
                   "': {}{:,.2f}')".format(PESO, rev)),
               "{} sale(s), {} piece(s) sold across the shop."
               .format(len(y_sales), pcs),
               "reports.category", None,
               Raw("CONCAT('daily_income:', "
                   "DATE_SUB(CURDATE(), INTERVAL 1 DAY))"),
               dt.datetime.combine(TODAY, dt.time(7, 30)))

if notif:
    w("INSERT INTO `notifications` (`user_id`,`type`,`severity`,`title`,`body`,"
      "`link_page`,`link_params`,`dedupe_key`,`created_at`) VALUES")
    w(",\n".join(notif) + ";")
w("")

w("-- Seed audit trail ---------------------------------------------------")
alog = []
d = TODAY - dt.timedelta(days=10)
aid = 0
while d <= TODAY:
    for _ in range(random.randint(2, 5)):
        aid += 1
        uid = random.choice([1, 2, 3, 4, 5])
        un = users[uid - 1][1]
        mod, act, desc = random.choice([
            ("Authentication", "LOGIN", "User signed in to the system"),
            ("Inventory", "UPDATE", "Updated stock quantity of an inventory item"),
            ("Inventory", "CREATE", "Registered a newly received surplus item"),
            ("Sales", "CREATE", "Completed a point-of-sale transaction"),
            ("Reports", "EXPORT", "Exported a sales summary report"),
        ])
        ts = dt.datetime.combine(d, dt.time(random.randint(8, 18), random.randint(0, 59)))
        alog.append(f"({uid},{q(un)},{q(mod)},{q(act)},{q(desc)},'127.0.0.1',{rel(ts)})")
    d += dt.timedelta(days=1)
w("INSERT INTO `audit_logs` (`user_id`,`username`,`module`,`action`,`description`,`ip_address`,`created_at`) VALUES")
w(",\n".join(alog) + ";")

open("/home/claude/aa-inventory/database/seed.sql", "w").write("\n".join(out) + "\n")
print("sales:", len(sales_rows), "items:", len(item_rows),
      "movements:", len(movements), "products:", len(products))
print("out of stock:", sum(1 for v in stock.values() if v == 0),
      "| low stock:", sum(1 for p in products if 0 < stock[p["id"]] <= p["reorder"]))
