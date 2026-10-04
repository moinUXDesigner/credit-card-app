# AI Statement Reading and Card Chat

## Summary
Use AI by default for PDF reading, retain local extraction as fallback, and add a read-only Card Chat page with saved conversations. Chat answers from all cards accessible to the user, selected statement PDFs, and current issuer research. Reuse Laravel/React, OpenAI configuration, statement review, permissions, and deterministic spending calculations.

## AI PDF reading
- Shared backend OpenAI Responses client: configurable models, timeouts, usage logging, safe errors, server-only credentials.
- Read every PDF page, including scans/rewards. Extract card identity/product, dates, total/minimum due, limit, closing rewards, and dated purchases/refunds with supported categories.
- Structured validated output; missing values remain null, hidden digits never reconstructed, repayments excluded. Document extraction performs no web research.
- Database-queued extraction outside database locks. Add a development/deployment worker. Analysis resources expose processing/ready/failed and ai/local source; authenticated polling retrieves results.
- Missing credentials, provider failure, malformed output or timeout use local extraction with limitations. Encrypted PDFs require unlocked copies.
- Reuse one extraction for Add Card and its first statement; retain the PDF. Preserve review/identity acknowledgement, duplicate protection, category edits and explicit import confirmation. Never reprocess completed imports automatically.

## Card Chat
- /chat navigation, private saved history, New chat/Delete, all-accessible/single-card scope, optional saved PDF selector.
- Answer about balances, utilization, dates, rewards, category spend, waivers, benefits and card selection. Suggested prompts.
- Narrow authorized data tools use fresh records and existing deterministic aggregation/recommendations.
- Inspect one selected PDF. Cite card/statement links and page references where available, distinguishing pending PDFs from confirmed spending.
- Issuer website research with clickable citations. Research requests contain only public issuer/product information, not balances, transactions, digits or account data.
- Read-only: no payments, card edits, category changes or imports through chat.
- Stable processing/error/retry UI, retained drafts, no duplicate messages, scroll only when near the bottom.

## Backend and storage
- Add conversations, messages, AI-request records with owner/context/status/references/timestamps/usage. Conversation deletion cascades messages.
- Authenticated list/create/read/delete conversation APIs, message submit and request polling; client-generated retry IDs.
- Queue chat, one active response per conversation, six AI submissions per minute per user, bounded history/tool output.
- Short persistence transactions; provider calls outside locks. Recheck authorization at job execution and result retrieval. Restrict history if referenced access is revoked; allow owner deletion/new chat.
- PDF/web/merchant text is untrusted. Sanitize message rendering/links. Keep offline PDF staging; chat is online-only and not queued by device sync.

## Validation and rollout
- Multi-page/scanned PDFs, rewards, masks, refunds/payments, validation, failure/fallback and no mutation before confirmation.
- Source retention, shared extraction, queue retries/polling/deduplication and import compatibility.
- Chat arithmetic/rankings/current context/issuer citations/PDF answers/private history/deletion/shared-card revocation.
- Mobile/stable refresh/retry/persistence browser checks. Apply additive migrations and run worker; verify real PDFs/chat with configured provider before enabling AI defaults. Log timing/failure/fallback/token usage without raw financial content.

Defaults: OpenAI; configurable server models; no vector database, streaming transport, voice, chat writes, or automatic changes to imported statements. Sources: https://developers.openai.com/api/docs/guides/file-inputs and https://developers.openai.com/api/docs/guides/tools-web-search.


## Implementation outcome — 4 October 2026

Implemented queued PDF extraction/local fallback, preview reuse, private persisted read-only Card Chat, authorized financial tools, isolated official issuer research, polling/retries, online-only chat, worker configuration and stalled-job recovery. Applied the additive migration and started the development worker. Backend: 190 tests / 680 assertions passed. Browser: 41 checks passed across the full run and targeted selector-fix rerun. Lint/build passed with existing dependency warnings. The local environment has no OpenAI key; live provider quality remains unverified and setup is documented in `docs/DEVELOPMENT_AND_DEPLOYMENT.md`.
