# profile-photos Specification

## Purpose

Respondents can attach a profile photo (PRD §15), and photos are stored and served safely (PRD §31).

## Requirements

### Requirement: Uploaded photos SHALL be stored only as server-generated files

The system SHALL accept JPG/PNG/WebP up to 2 MB, re-encode them with GD where the codec is available (otherwise keep the bytes only after `getimagesize` confirms the type), and store them on the private disk under `testimonial-photos/{space_id}/{uuid}.{ext}`. Image fields SHALL never accept a client-supplied path.

#### Scenario: disguised file

- **WHEN** a file named `.jpg` is not a decodable image
- **THEN** the response is 422 and nothing is stored

### Requirement: Photos SHALL be served only to permitted viewers

`GET /photos/{value}` SHALL serve the file to the Space owner, or to anyone when the testimonial is publicly visible and the photo field has `show_in_embed = true`; otherwise it SHALL respond 404. Responses SHALL carry the image Content-Type, `X-Content-Type-Options: nosniff` and `Content-Security-Policy: default-src 'none'`.

#### Scenario: hidden testimonial

- **WHEN** the testimonial is hidden
- **THEN** a non-owner gets 404

#### Scenario: non-photo value

- **WHEN** the stored value is not a server-generated photo path
- **THEN** the response is 404, even for the owner
