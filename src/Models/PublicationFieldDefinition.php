<?php

declare(strict_types=1);

namespace Hyde\Publications\Models;

use function array_filter;
use function array_merge;
use Hyde\Publications\PublicationFieldTypes;
use Hyde\Support\Concerns\Serializable;
use Hyde\Support\Contracts\SerializableContract;
use Illuminate\Support\Str;
use function str_contains;
use function strtolower;

/**
 * Represents an entry in the "fields" array of a publication type schema.
 *
 * @see \Hyde\Publications\Models\PublicationFieldValue
 * @see \Hyde\Publications\PublicationFieldTypes
 * @see \Hyde\Publications\Testing\Feature\PublicationFieldDefinitionTest
 */
class PublicationFieldDefinition implements SerializableContract
{
    use Serializable;

    public readonly PublicationFieldTypes $type;
    public readonly string $name;
    public readonly array $rules;
    public readonly ?string $tagGroup;

    public static function fromArray(array $array): static
    {
        return new static(...$array);
    }

    public function __construct(PublicationFieldTypes|string $type, string $name, array $rules = [], ?string $tagGroup = null)
    {
        $this->type = $type instanceof PublicationFieldTypes ? $type : PublicationFieldTypes::from(strtolower($type));
        $this->name = str_contains($name, ' ') ? Str::kebab($name) : Str::ascii($name);
        $this->rules = $rules;
        $this->tagGroup = $tagGroup;
    }

    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type->value,
            'name' => $this->name,
            'rules' => $this->rules,
            'tagGroup' => $this->tagGroup,
        ]);
    }

    /**
     * Get the validation rules for this field.
     *
     * @return array<string> The type default rules merged with any custom rules.
     */
    public function getRules(): array
    {
        return array_merge($this->type->rules(), $this->rules);
    }
}
