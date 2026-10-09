# space-themes Specification

## Purpose

PRD §10: three predefined themes that change the public pages.

## Requirements

### Requirement: Spaces SHALL use one of the themes `minimal`, `modern`, `clean`

The system SHALL offer exactly these themes, each with its own color tokens, radius and color scheme under `.space-theme-{value}`, applied to the public submission page and the Wall of Love. Existing values SHALL be migrated `minimal_light → minimal`, `minimal_dark → modern`, `soft_color → clean`.

#### Scenario: picker

- **WHEN** the owner opens Create or Settings
- **THEN** the theme picker lists Minimal, Modern and Clean with a short description
