# Photo suggestions

This release adds draft **names, categories and descriptions**, plus a reviewed **initial selling-price suggestion**, to the Add and Edit Inventory Item forms. Manager-only discount approval is unchanged.

## Staff flow

1. On a phone, tap **Start with a product photo**, then **Take photo** to request the rear camera, or **Choose from gallery** to select an existing picture. Choose a clear JPG, PNG or WEBP photo up to 6 MB. Camera capture depends on the phone/browser; unsupported devices show a file picker instead. HEIC files must be exported as JPG before upload. Permission is handled by the phone/browser.
2. Click **Suggest details from photo**. This sends the selected photo and the category list to Google Gemini. Nothing is sent automatically on photo selection.
3. Review the suggested name, category, description and initial price. Suggestions can be inaccurate. Verify the actual item; a picture cannot establish working condition or specifications. The AI does not invent a price: the server uses similar local sales or a labeled demo placeholder.
4. Tick the fields to use, then click **Use selected details**. Existing entries are unticked by default. If the category is uncertain, select it manually.
5. The price checkbox is always unticked initially. Tick it only if you want to copy the suggestion, then edit the price and other fields before saving normally. Generating suggestions never saves an item or changes stock or condition.

## Initial prices for the demo

The **Suggest initial price** button works without an AI key. Enter a name and choose a category, request the suggestion, review the source, then click **Use suggested price**. Existing prices stay unchanged until you choose to apply a suggestion. Changing the name or category discards a stale price suggestion.

The system checks up to 200 recent sales lines in the selected category, matches meaningful words in product names, and suggests the median net unit price among the closest matches. Sale discounts and quantities are included. This is a simple initial reference, not a condition-aware appraisal. Older sales that lack category snapshots use the product's current category, with that limitation shown in the explanation.

With no matching sales, the demo suggests **₱500.00**, labeled **Demo placeholder only**. This is an arbitrary demonstration amount, not a researched market price. Change `demo_price` in `config/photo-ai.php` to use another demo amount. Set `demo_pricing` to false, or the `SMARTSTOCK_DEMO_PRICING` environment variable to `0`, to require manual pricing when history has no matches.

Acquisition cost is never generated or overwritten. Existing item-price approval rules still apply when saving a changed selling price.

When editing, select a photo again to generate a draft. The previously stored photo is kept until a replacement is saved. Dismiss leaves entered details untouched. Changing the selected photo discards the previous draft.

The phone opens the same server installation as the office. Staff sign in with their own account, review the draft, save the product, then open its details to print the generated labels. Printing from a phone needs access to a printer or can be done later from the office computer. Taking a product picture is separate from QR-code camera scanning; live QR camera scanning has not been added.

## Enable on the server

Photo-generated text ships disabled until a key is configured. Manual entry and local initial-price suggestions work without it.

1. Obtain a Gemini API key in [Google AI Studio](https://aistudio.google.com/apikey). Check your provider account's usage and billing settings.
2. In `config/photo-ai.php`, replace the empty fallback in `'api_key' => getenv('SMARTSTOCK_GEMINI_API_KEY') ?: ''` with your key **on your server only**. Alternatively set the `SMARTSTOCK_GEMINI_API_KEY` environment variable for Apache/PHP and restart Apache. Do not paste the key into JavaScript or share a configured source ZIP.
3. The default model is `gemini-3.8-flash`. `SMARTSTOCK_AI_MODEL` can select another model that supports image input and structured output through the Interactions API.
4. Enable PHP cURL and HTTPS outbound access. Leave TLS certificate verification enabled. If XAMPP reports certificate errors, configure PHP's `curl.cainfo` using an up-to-date trusted CA bundle and restart Apache.
5. For uploads up to 6 MB, set PHP `upload_max_filesize=6M`, `post_max_size=8M`, and a writable `upload_tmp_dir`. Restart Apache after editing `php.ini`.
6. Sign in, choose a product photo and test a live draft. Verify that the text is useful, acquisition cost stays unchanged, selling price only changes after explicit selection, and saving creates the intended record.

Requests have a 50-second provider timeout and a 15-second session cooldown. Invalid photos, missing configuration, provider failures and exhausted allowances show a manual-entry fallback. The key stays server-side. The request uses `store=false`; provider processing still follows its terms.

Implementation references: [image input](https://ai.google.dev/gemini-api/docs/image-understanding), [structured output](https://ai.google.dev/gemini-api/docs/structured-output), [Interactions API](https://ai.google.dev/api/interactions-api).

## Validation limits

Upload, response validation, permission/CSRF checks and review controls were tested locally. History calculations and the demo fallback were tested against an isolated database and in the browser. Photo UI success tests used a controlled response fixture. Live AI identification was not tested because no service key is configured. Real product photos and the store's provider account still need acceptance testing.

The later Take photo / Choose from gallery controls passed PHP and JavaScript syntax checks. Their latest browser preview could not be completed because the isolated local test page was unreachable. Physical Android/iPhone camera capture and printing have not been tested. Camera selection uses the browser's [HTML capture feature](https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Attributes/capture), with ordinary file upload available as a fallback.

For the latest photo-first layout and its verification limits, see UI-UPDATE.md.
