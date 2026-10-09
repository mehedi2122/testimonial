# Tasks: space-created-page

- [x] 1.1 `Space::publicSubmissionUrl()` and `publicWallUrl()`
- [x] 1.2 `GET /spaces/{space}/created` → `SpaceController::created`, owner-only
- [x] 1.3 `POST /spaces` redirects to `spaces.created`
- [x] 1.4 Inbox receives `public_url` (fixes the `/s/{slug}` 404 link)
- [x] 2.1 `components/copy-button.tsx`; Embed page uses it
- [x] 2.2 `pages/spaces/created.tsx` — public link, Copy link, View Space, next steps
- [x] 3.1 Tests: redirect target, page props, link resolves to the form, 403 non-owner, guest redirect, inbox `public_url`
- [x] 3.2 Quality gates: pint, phpstan, npm run check, php artisan test (328 passing)
