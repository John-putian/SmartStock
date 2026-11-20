# Smart Stock — Normalization video: voice-over script

Length **3:53**. Every line starts at the time shown. The captions in the video are this script word for word, so read along with them.

**How to record the voice:** open `SmartStock_Normalization_captions.mp4` in Clipchamp (built into Windows) or CapCut → *Record* → *Voiceover*, and read each caption as it appears. If you would rather not follow the captions, use `SmartStock_Normalization_clean.mp4` (no captions) and read at a relaxed pace.

| Time | On screen | Say |
|---|---|---|
| 0:01 | UNF table | Hi everyone. In this video, we show how the sales data of Smart Stock, our inventory and point of sale system for A&A Surplus, is organized through normalization in MariaDB. |
| 0:14 | UNF table | This first table is our starting point. It copies the way a cashier would write sales by hand: one line per receipt, with everything sold squeezed into that line. Look at the item columns. One cell holds two or three SKUs, two or three names, two or three quantities. Nothing here uniquely identifies a row, and a single cell holds a whole list. That is unnormalized form. |
| 0:44 | 1NF table | To reach first normal form, we give every item its own row, so each cell keeps just one value. Take invoice 1095. It had three pieces of furniture, so now it takes up three rows. A row is identified by two columns together: the invoice number and the SKU. |
| 1:05 | 1NF table | The lists are gone, but look at how much is repeated. The same cashier's name shows up on line after line, and so do the same customers and products. If one of those details changed, every copy would have to be fixed, and missing even one would leave the data inconsistent. |
| 1:28 | 2NF · nf2_sales | Second normal form deals with columns that depend on only part of the key. The sale date, the cashier, the customer and the payment belong to the receipt alone, so they now sit in their own table, one row per invoice. |
| 1:46 | 2NF · nf2_products | Product details belong to the SKU alone, so each product is now listed one time. |
| 1:54 | 2NF · nf2_sale_items | What stays behind are the facts that need both the invoice and the SKU: how many pieces were sold, and at what price. We keep the price on the sale line, since prices in Smart Stock can change after an approval. |
| 2:12 | 3NF · users | Third normal form removes values that depend on another non-key value. A cashier's full name and role are determined by the username, not by the receipt, so the cashiers get their own users table. |
| 2:27 | 3NF · categories | The same idea applies to categories. The category code follows the category name, so it moves to a categories table. |
| 2:37 | 3NF · products | Each product now carries only a category ID that points to that table. |
| 2:43 | 3NF · sales | Each sale carries a user ID that points to the cashier, |
| 2:49 | 3NF · sale_items | and each sale line points to both its sale and its product. These links give us one-to-many relationships: a category with many products, a cashier with many sales, and a sale with many lines. |
| 3:04 | Proof · lossless join | Did we lose anything by splitting the data? To check, we join every third normal form table back together and compare the result with the first normal form table. Both counts are zero, so the original data comes back exactly. |
| 3:22 | Proof · live foreign keys | Finally, this is the real Smart Stock database. Its foreign keys follow the same design: products refer to categories, sales refer to users, and sale items refer to sales and products. |
| 3:36 | Proof · live foreign keys | With the data normalized, every fact is stored in one place, changes happen once, and every receipt stays tied to the correct cashier, product and category. That completes our normalization walkthrough. Thank you for watching. |

## Plain script (to read from)

**[0:01]** Hi everyone. In this video, we show how the sales data of Smart Stock, our inventory and point of sale system for A&A Surplus, is organized through normalization in MariaDB.

**[0:14]** This first table is our starting point. It copies the way a cashier would write sales by hand: one line per receipt, with everything sold squeezed into that line. Look at the item columns. One cell holds two or three SKUs, two or three names, two or three quantities. Nothing here uniquely identifies a row, and a single cell holds a whole list. That is unnormalized form.

**[0:44]** To reach first normal form, we give every item its own row, so each cell keeps just one value. Take invoice 1095. It had three pieces of furniture, so now it takes up three rows. A row is identified by two columns together: the invoice number and the SKU.

**[1:05]** The lists are gone, but look at how much is repeated. The same cashier's name shows up on line after line, and so do the same customers and products. If one of those details changed, every copy would have to be fixed, and missing even one would leave the data inconsistent.

**[1:28]** Second normal form deals with columns that depend on only part of the key. The sale date, the cashier, the customer and the payment belong to the receipt alone, so they now sit in their own table, one row per invoice.

**[1:46]** Product details belong to the SKU alone, so each product is now listed one time.

**[1:54]** What stays behind are the facts that need both the invoice and the SKU: how many pieces were sold, and at what price. We keep the price on the sale line, since prices in Smart Stock can change after an approval.

**[2:12]** Third normal form removes values that depend on another non-key value. A cashier's full name and role are determined by the username, not by the receipt, so the cashiers get their own users table.

**[2:27]** The same idea applies to categories. The category code follows the category name, so it moves to a categories table.

**[2:37]** Each product now carries only a category ID that points to that table.

**[2:43]** Each sale carries a user ID that points to the cashier,

**[2:49]** and each sale line points to both its sale and its product. These links give us one-to-many relationships: a category with many products, a cashier with many sales, and a sale with many lines.

**[3:04]** Did we lose anything by splitting the data? To check, we join every third normal form table back together and compare the result with the first normal form table. Both counts are zero, so the original data comes back exactly.

**[3:22]** Finally, this is the real Smart Stock database. Its foreign keys follow the same design: products refer to categories, sales refer to users, and sale items refer to sales and products.

**[3:36]** With the data normalized, every fact is stored in one place, changes happen once, and every receipt stays tied to the correct cashier, product and category. That completes our normalization walkthrough. Thank you for watching.
