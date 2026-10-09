# space-created-page — Proposal

## Why

PRD §11 says that after creating a Space the owner sees a success page with the public URL, a Copy Link button and a View Space button. Today `POST /spaces` redirects straight to the dashboard, and the only public link in the app (the Inbox empty state) points at `/s/{slug}`, which 404s because the submission form is keyed by `public_id`.

## What changes

1. `GET /spaces/{space}/created` renders `spaces/created`: the public submission URL (`/s/{public_id}`) with Copy link and View Space, the Wall of Love URL, and links to the dashboard, inbox and embed builder. Owner-only (`SpacePolicy::view`).
2. `POST /spaces` redirects there instead of the dashboard.
3. `Space::publicSubmissionUrl()` / `publicWallUrl()` are the single place that builds public URLs. The Inbox gets `public_url` from the server, which fixes the 404 link.
4. A shared `CopyButton` component (extracted from the Embed page) backs both copy buttons.

## Impact

- Routes: one new authenticated GET.
- Schema: none.
