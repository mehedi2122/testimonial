<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\SpaceFieldMode;
use App\Enums\SpaceFieldType;
use App\Models\Space;
use App\Models\SpaceField;
use App\Rules\ReservedFieldKey;
use App\Rules\SubmissionValuesForSpace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * Validation for the public testimonial submission endpoint (OpenSpec
 * changes: public-testimonial-submission, public-submission-fixes).
 *
 * Accepts JSON or multipart (multipart when a profile photo is
 * attached). Field answers arrive as `values[] = {field_key, value}`;
 * image-type fields arrive only as files under `photos[field_key]` —
 * a client can never name a storage path itself.
 *
 * `consent_given` is optional (PRD §13); without it the testimonial is
 * stored but can never be public (data-model §15).
 *
 * Required fields are enforced here, server-side (PRD §13): every
 * `mode = required` field must have a non-empty value or an uploaded
 * photo.
 */
class SubmitTestimonialRequest extends FormRequest
{
    /** @var Collection<int, SpaceField>|null */
    private ?Collection $fields = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * An unknown or soft-deleted Space is a 404 before any field
     * validation — otherwise the field checks would answer with a
     * misleading 422 about fields "not defined on this Space".
     */
    protected function prepareForValidation(): void
    {
        if ($this->resolvedSpace() === null) {
            throw new HttpResponseException(
                response()->json(['error' => 'space_not_found'], 404),
            );
        }
    }

    /**
     * Resolve the Space from the route parameter. Reused by rating-conditional logic and
     * by the controller for the SoftDeletes preflight and plan-limit checks.
     */
    public function space(): Space
    {
        /** @var Space $space */
        $space = Space::query()
            ->where('public_id', $this->route('public_id'))
            ->whereNull('deleted_at')
            ->firstOrFail();

        return $space;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $space = $this->resolvedSpace();

        $ratingRequired = $space instanceof Space && $space->rating_enabled;

        // field_key -> type map for the per-type SubmissionValue rule. Only
        // the resolved Space's space_fields count, so a value targeting a
        // field_key not on this Space is rejected as unknown.
        $fieldTypeMap = $this->fields()
            ->mapWithKeys(fn (SpaceField $field): array => [$field->field_key => $field->type->value])
            ->all();

        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc'],
            'testimonial' => ['required', 'string', 'min:10', 'max:2000'],
            'rating' => [$ratingRequired ? 'required' : 'sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'consent_given' => ['sometimes', 'boolean'],
            // `is_wall_of_love` is deliberately absent: only the owner
            // publishes (PRD §17). The action always stores false.
            'values' => ['sometimes', 'array', new SubmissionValuesForSpace($fieldTypeMap)],
            'values.*' => ['array:field_key,value'],
            'values.*.field_key' => ['required', 'string', 'max:64', new ReservedFieldKey],
            'values.*.value' => ['required'],
            'photos' => ['sometimes', 'array'],
            'photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=6000,max_height=6000'],
        ];
    }

    /**
     * Cross-field checks that need the Space's field configuration.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $fields = $this->fields()->keyBy('field_key');
                $answered = $this->answeredKeys();
                $photos = $this->photoFiles();

                foreach (array_count_values($this->submittedKeys()) as $key => $count) {
                    if ($count > 1) {
                        $validator->errors()->add("fields.{$key}", 'Each field can only be answered once.');
                    }
                }

                foreach ($answered as $key) {
                    $field = $fields->get($key);
                    if ($field?->type === SpaceFieldType::Image) {
                        $validator->errors()->add("fields.{$key}", 'Upload the photo as a file.');
                    } elseif ($field?->mode === SpaceFieldMode::Off) {
                        // The owner turned this field off; the form never
                        // offers it, so an answer can only be crafted.
                        $validator->errors()->add("fields.{$key}", 'This Space does not ask for that field.');
                    }
                }

                foreach (array_keys($photos) as $key) {
                    $key = (string) $key;
                    $field = $fields->get($key);
                    if ($field === null || $field->type !== SpaceFieldType::Image || $field->mode === SpaceFieldMode::Off) {
                        $validator->errors()->add("photos.{$key}", 'This Space does not accept that photo.');
                    }
                }

                foreach ($fields as $key => $field) {
                    if ($field->mode !== SpaceFieldMode::Required) {
                        continue;
                    }

                    $provided = $field->type === SpaceFieldType::Image
                        ? isset($photos[$key])
                        : in_array($key, $answered, true);

                    if (! $provided) {
                        $validator->errors()->add("fields.{$key}", "{$field->label} is required.");
                    }
                }
            },
        ];
    }

    /**
     * Uploaded photos keyed by field_key, after validation (`photos.*`
     * is validated as a file). PHP turns numeric-string keys into ints,
     * hence int|string.
     *
     * @return array<int|string, UploadedFile>
     */
    public function photoFiles(): array
    {
        $files = $this->file('photos');

        if (! is_array($files)) {
            return [];
        }

        $photos = [];
        foreach ($files as $key => $file) {
            $photos[strtolower((string) $key)] = $file;
        }

        return $photos;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'values.*.field_key.required' => 'Each submission value must include a field_key.',
            'values.*.value.required' => 'Each submission value must include a value.',
            'photos.*.image' => 'The photo must be an image.',
            'photos.*.mimes' => 'Use a JPG, PNG or WebP photo.',
            'photos.*.max' => 'The photo must be 2 MB or smaller.',
        ];
    }

    /**
     * field_keys with a non-empty submitted value.
     *
     * @return list<string>
     */
    private function answeredKeys(): array
    {
        $values = $this->input('values', []);

        if (! is_array($values)) {
            return [];
        }

        $keys = [];
        foreach ($values as $entry) {
            if (is_array($entry) && isset($entry['field_key']) && trim((string) ($entry['value'] ?? '')) !== '') {
                $keys[] = strtolower((string) $entry['field_key']);
            }
        }

        return $keys;
    }

    /**
     * Every submitted field_key, including empty answers.
     *
     * @return list<string>
     */
    private function submittedKeys(): array
    {
        $values = $this->input('values', []);

        if (! is_array($values)) {
            return [];
        }

        $keys = [];
        foreach ($values as $entry) {
            if (is_array($entry) && isset($entry['field_key'])) {
                $keys[] = strtolower((string) $entry['field_key']);
            }
        }

        return $keys;
    }

    private function resolvedSpace(): ?Space
    {
        return $this->route('public_id')
            ? Space::query()->where('public_id', $this->route('public_id'))->whereNull('deleted_at')->first()
            : null;
    }

    /**
     * @return Collection<int, SpaceField>
     */
    private function fields(): Collection
    {
        if ($this->fields === null) {
            $space = $this->resolvedSpace();
            $this->fields = $space instanceof Space ? $space->fields()->get() : new Collection;
        }

        return $this->fields;
    }
}
