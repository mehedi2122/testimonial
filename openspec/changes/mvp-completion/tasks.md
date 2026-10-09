# Tasks: mvp-completion

- [x] 1.1 Public form: fetch + FormData, JSON error mapping, success card, optional consent, photo picker, theme wrapper
- [x] 1.2 Request: optional consent, required/off/duplicate/image-value checks, 404 before validation, no `is_wall_of_love`
- [x] 1.3 Sanitizer stores plain text; decode migration
- [x] 2.1 Address predefined field + backfill migration
- [x] 2.2 `UpdateSpaceFieldModesAction` + `FieldModesEditor` on Create and Settings
- [x] 3.1 Photo store/access actions, `GET /photos/{value}`, photos in inbox, wall, embed and builder preview
- [x] 4.1 Themes `minimal|modern|clean` + CSS + migration; `@theme inline`
- [x] 5.1 Inbox: consent badge, Wall of Love consent gate, `…` menu
- [x] 6.1 Security: http(s)-only URLs, webhook fail-closed, `auth.user` allowlist, duplicate field_key → 422
- [x] 7.1 Mobile: embed grid overflow, inbox header wrap
- [x] 8.1 Tooling: types:check fixed, unused imports removed, MySQL CI job
- [x] 9.1 Tests: 362 passing; pint, phpstan, npm run check, types:check and build all pass
- [ ] 9.2 MySQL CI job green on GitHub — _owner to run (no MySQL locally; the folder is not a git repository)_
