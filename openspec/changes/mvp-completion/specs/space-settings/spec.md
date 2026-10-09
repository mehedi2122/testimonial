# space-settings — delta

## ADDED Requirements

### Requirement: Owners SHALL configure each form field as Off, Optional or Required

The Create and Settings pages SHALL show every Space field with an Off / Optional / Required control (Name and Email are always required). Submitted modes SHALL apply only to this Space's live fields; unknown keys are ignored. New Spaces seed Address as Required (private by default) and the other predefined fields as Off.

#### Scenario: owner makes Company required

- **WHEN** the owner saves Settings with `fields.company_name = required`
- **THEN** the public form requires Company name

#### Scenario: invalid mode

- **WHEN** a mode other than off/optional/required is sent
- **THEN** validation fails on `fields.{key}`
