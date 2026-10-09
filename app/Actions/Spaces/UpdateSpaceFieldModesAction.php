<?php

declare(strict_types=1);

namespace App\Actions\Spaces;

use App\Enums\SpaceFieldMode;
use App\Models\Space;
use App\Models\SpaceField;

/**
 * Owner-side field configuration (PRD §8): each field is Off,
 * Optional or Required. Used by both Space creation and Settings.
 *
 * Only this Space's live fields are touched; unknown keys are ignored
 * rather than created (custom-field authoring is a separate flow).
 * Name and Email are columns, always required, and not configurable.
 */
class UpdateSpaceFieldModesAction
{
    /**
     * @param  array<string, string|SpaceFieldMode>  $modes  field_key => mode
     */
    public function apply(Space $space, array $modes): void
    {
        if ($modes === []) {
            return;
        }

        $space->fields()
            ->whereIn('field_key', array_keys($modes))
            ->get()
            ->each(function (SpaceField $field) use ($modes): void {
                $mode = $modes[$field->field_key];
                $field->mode = $mode instanceof SpaceFieldMode ? $mode : SpaceFieldMode::from($mode);
                $field->save();
            });
    }

    /**
     * Fields as the owner-facing editor expects them.
     *
     * @return list<array{field_key: string, label: string, type: string, mode: string}>
     */
    public function describe(Space $space): array
    {
        return array_values($space->fields()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (SpaceField $field): array => [
                'field_key' => $field->field_key,
                'label' => $field->label,
                'type' => $field->type->value,
                'mode' => $field->mode->value,
            ])
            ->all());
    }

    /**
     * The predefined fields with their default modes, for the create form.
     *
     * @return list<array{field_key: string, label: string, type: string, mode: string}>
     */
    public function defaults(): array
    {
        return array_map(
            fn (array $row): array => [
                'field_key' => $row['field_key'],
                'label' => $row['label'],
                'type' => $row['type']->value,
                'mode' => $row['mode']->value,
            ],
            SpaceField::PREDEFINED_FIELDS,
        );
    }
}
