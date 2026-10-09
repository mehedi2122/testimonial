# mvp-completion — Proposal

## Why

A browser walk-through of the full PRD §36 flow found the public form broken, plus several PRD gaps and security issues:

- **Public submission was broken.** The form posted with Inertia `useForm` to a JSON endpoint: any Space with a custom field failed ("values.x must be an array"), and successful 201s never showed the thank-you card.
- **Text displayed as `&amp;`.** Text was entity-encoded on write (`htmlspecialchars`) _and_ escaped on render.
- **PRD gaps:** consent must be optional (§13); Address is a default required field (§8); owners must be able to configure fields (§8); profile photo upload (§15); themes Minimal / Modern / Clean that actually change the public page (§10); `…` actions menu in the inbox (§17).
- **Security review findings:** a submitter could self-publish with `is_wall_of_love=1`; answers were accepted for fields the owner turned off; `javascript:` URLs passed validation; the Stripe webhook accepted unsigned requests when no secret was configured; every page shared the full `users` row; a repeated `field_key` caused a 500.

## What changes

1. Public form posts with `fetch` + `FormData` (multipart) and maps the JSON 201/422/429/404 back onto fields. Whole page wrapped in `space-theme-{theme}`.
2. `SubmitTestimonialRequest`: consent optional; required fields enforced server-side; off-field answers, duplicate keys and text values for image fields rejected; `is_wall_of_love` no longer accepted (always stored `false`); 404 for unknown Spaces before field validation.
3. `SanitizesFreeText`: strip tags and control characters, store plain text. A migration decodes previously encoded rows.
4. Address predefined field (on, required, `show_in_embed = false`); backfilled as `off` on existing Spaces.
5. `UpdateSpaceFieldModesAction` + `FieldModesEditor` on Create and Settings.
6. Profile photos: `StoreTestimonialPhotoAction` re-encodes with GD to a private disk path; `GET /photos/{value}` serves via `ResolvePhotoAccessAction` (owner, or public testimonial + `show_in_embed`) with `nosniff` and a deny-all CSP. Shown in the inbox, wall, embed and builder preview.
7. Themes renamed `minimal|modern|clean` (migration maps the old values) with real CSS token sets; Tailwind `@theme inline` so tokens follow the nearest scope.
8. Inbox: consent badge, Wall of Love blocked without consent (server and UI), `…` menu with Edit / Hide-Show / Delete.
9. URL fields: only `http(s)`; the wall renders anything else as text.
10. Stripe webhook fails closed (503) outside local/testing without a signing secret.
11. Shared `auth.user` is an explicit allowlist.
12. Mobile: Embed page grid overflow fixed; inbox header wraps.
13. Tooling: `npm run types:check` fixed (missing `PageProps`, untyped shared props); unused imports removed; GitHub Actions gains a MySQL 8 job (PRD §2).

## Impact

- Migrations: `rename_space_themes`, `add_address_field_to_existing_spaces`, `decode_html_entities_in_testimonials`.
- Routes: `GET /photos/{value}`.
- API behaviour change: `is_wall_of_love` in a submission is ignored; `consent_given` may be false or absent.
