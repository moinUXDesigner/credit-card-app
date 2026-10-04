# Card navigation, statement imports, and accurate utilization

Approved implementation plan — 3 October 2026.

## Summary
Extend the existing React/Laravel implementation. Clicking a card opens its detail page; users upload and review statements there. A PDF used during Add Card becomes the first saved statement after confirmation.

Inspection confirmed a limited navigation click area, missing outstanding-balance autofill, narrow fallback last-four extraction, immediate statement application, duplicate spend, historical balance overwrites, and stale card details after uploads.

## Navigation and card forms
- Keep `/cards/:id` and `/cards/:id/edit`; make main card content accessible navigation with separate Edit/Delete controls.
- Add Upload statement linking to `/cards/:id?tab=statements`; preserve tabs in URL.
- Reproduce navigation failure; provide loading, unavailable/access-denied, and retryable error states.
- Extract/sanitize current_outstanding from total outstanding/total due, never minimum due/available credit/transaction sums.
- Expand masked last-four extraction; preserve leading zeros and require manual entry when ambiguous.
- Apply autofill without remounting or losing dirty fields; show missing/fallback warnings.
- Preview balance/limit utilization; retain shared-limit rules and 30/50/75 bands. Unknown/zero limits cannot calculate; over-limit balances stay valid.

## Reviewed statement workflow and interfaces
- Shared extraction result: last four, dates, limit, total/minimum due, rewards, transaction rows, warnings.
- Authenticated POST /statement-previews and GET /statement-previews/{id}; private temporary user-bound drafts, optional existing card, no financial changes.
- POST /cards/{card}/statements confirms preview_id, reviewed period/rows/summary selections and idempotency key. Legacy/raw offline uploads stage review rather than apply.
- Editable review includes destination identity, dates/totals/rows/warnings. Identity mismatch blocks; absent identity requires acknowledgement. Likely duplicate rows require skip/keep decisions.
- Summary/limit updates explicitly selected; shared-limit validation preserved. Failed extraction supports retry or Save PDF only; encrypted PDFs request an unlocked copy; partial extraction is warned.
- Add Card retains PDF draft, creates card, imports draft, then opens Statements. Partial attachment/benefit failure retries targeted work without recreating card.
- Additive migrations for drafts/fingerprints/metadata/receipts/summary provenance; legacy provenance unknown.

## Data integrity, permissions, and sync
- Atomic confirmation, locks, validation and authorization rechecks; durable idempotency receipts and unique card/file fingerprints.
- Exact PDF duplicate returns existing statement; overlapping date/description/amount/direction rows reviewed, not silently dropped.
- Reuse purchase/credit semantics and SpendAggregationService; preserve manual spend; exclude payments from purchases.
- Only newer statements update summaries by default; retain statement/manual/message provenance and prevent silent overwrite of newer nonstatement values.
- Deletion recomputes spend/waivers and restores eligible prior summary only when deleted statement still supplies it.
- Refresh card and affected cached aggregate data after imports/deletion.
- Owner/editor upload, viewer read/download; drafts expire after 24 hours and abandoned files cleaned.
- Preserve offline file queue, 100 MB quota, temporary card dependencies, receipt/revision/conflict handling. Reconnection stages analysis and requires review; queued confirmed work is idempotent.

## Validation and delivery
- Browser navigation/edit/tab/errors/review/Add Card retry/permissions/refreshed utilization.
- Extraction masks/leading zero/ambiguity/totals/fallback/encryption/partial output.
- Backend draft isolation/atomic confirmation/retries/duplicates/historical summaries/deletion/manual spend/shared limits.
- Sync reconnect/review/confirmation/dependencies/expiry/quota/revocation/conflicts.
- Relevant backend/browser tests, lint, production build; authorized real-file verification separate from mocked/provider checks.
- Update ledger DEV-04/07/16/17/23 with evidence.

Defaults: existing 15 MB PDF limit; photos autofill-only. No configurable usage target, payments, automatic inbox integration, Android or deployment.

## Implementation verification — 3 October 2026

Implemented the reviewed upload lifecycle, card navigation/deep links, outstanding/last-four autofill, utilization preview, duplicate and chronology guards, summary deletion recovery, permissions, and offline staging/confirmation. Applied the additive migration to the local development database.

Validation: 171 backend tests (574 assertions), 25 browser tests, frontend lint and production build. Local text extraction checked all four project PDFs. Both SBI statements expose only two ending digits, which remain unknown for the required four-digit field. Live AI transaction extraction and hosted deployment were not performed. The existing upstream Lottie eval and bundle-size build warnings remain.

## Follow-up: product title and rewards autofill

Read every PDF page for the actual card product masthead and closing/available reward balance. Extend the text fallback for labeled reward balances and SBI Shop & Smile summaries, retaining bounded account-summary extraction and rejecting conflicting values. Prefill existing card-name/reward form fields through the existing analysis response. Verify against the supplied BPCL Octane PDF and multi-page/zero/ambiguous regression fixtures.

Verified supplied PDF extraction: BPCL SBI Card OCTANE, closing reward balance 18,791. Extraction/provider unit tests: 14 passed (53 assertions).

## Follow-up: recorded payments and recent-spends UI

Add a full/partial Mark as paid dialog on card tiles and details. Save remaining outstanding through the existing revision-checked card update API, marking the balance as manually maintained and refreshing utilization. Validate positive amounts to two decimal places, cap at outstanding, hide for viewers, and disable at zero. Present recent spends and an unavailable total without supplying invented transactions; data-source integration follows the user’s next instructions. Verify cancellation, full/partial payments, validation, permissions, and conflicts in browser tests.

Payment verification: 13 statement/payment browser tests passed, 11 card API tests passed (39 assertions), frontend lint/build passed. Existing Lottie/bundle-size warnings remain.

## Follow-up: statement review without AI — 4 October 2026

Use local pdftotext extraction for statement review regardless of API-key configuration. Reuse bounded summary parsing, return full validated dates, minimum due, outstanding, credit limit, rewards and visible identity. Billing month/year derive from the statement date in the existing UI. Parse supported dated transaction rows conservatively, exclude payments, and warn about incomplete extraction. Missing/unreadable fields stay editable; encrypted/image-only files require an unlocked/text-readable copy. Verify the supplied PDF and no-provider-call regression tests.

Local PDF verification: all six statement summary inputs and derived billing period verified against the BPCL PDF; unavailable identity stays blank. No outbound provider call asserted in local extraction tests.

## Follow-up: retain the Add Card source statement — 4 October 2026

Always attach the source PDF after creating a card. Accepted review follows reviewed confirmation; unaccepted/canceled review stages the PDF as pending with no summary/spend updates. Preserve retained files on preview failures and retain retry/idempotent upload behavior. Navigate to Statements once attachment succeeds. Recover the retained card-bound BPCL PDF for card 56 as pending review without altering balances or spending.

## Follow-up: editable transaction category column — 4 October 2026

Replace the expanded transaction list with a horizontally scrollable table using Date, Description, Category and Amount columns. Click the category chip to choose a supported spend category, then Save or Cancel. Persist via a statement-scoped category-only endpoint with owner/editor authorization and validated categories. Recompute the transaction month’s category totals while preserving manual spending and outstanding. Verify persistence/reload/cancel, permissions and cross-statement isolation.

## Follow-up: stable refreshes while editing — 4 October 2026

Keep loaded card/statement content mounted during background reloads; reserve skeletons/loading rows for initial requests. Remove redundant statement reload after sync-data broadcasts. Ignore stale responses and invalidate pending requests on route/unmount changes. Preserve card content during transient refresh errors while revoking access on 403/404. Reserve scrollbar space. Verify category editor position/value and partial-payment dialogs survive delayed refreshes, alongside statement/payment regression tests.
