<?php

declare(strict_types=1);

namespace Hyde\Publications\Actions;

use function array_merge;
use function collect;
use Hyde\Publications\Models\PublicationFieldDefinition;
use Hyde\Publications\Models\PublicationType;
use Hyde\Publications\PublicationFieldTypes;
use Hyde\Publications\PublicationService;
use Illuminate\Contracts\Validation\Validator;
use function validator;

/**
 * @see \Hyde\Publications\Testing\Feature\PublicationFieldValidatorTest
 */
class PublicationFieldValidator
{
    protected PublicationType $publicationType;
    protected PublicationFieldDefinition $fieldDefinition;

    public function __construct(PublicationType $publicationType, PublicationFieldDefinition $fieldDefinition)
    {
        $this->publicationType = $publicationType;
        $this->fieldDefinition = $fieldDefinition;
    }

    public function getValidationRules(): array
    {
        return array_merge(
            $this->fieldDefinition->getRules(),
            $this->makeDynamicRules()
        );
    }

    /** @throws \Illuminate\Validation\ValidationException */
    public function validate(mixed $input = null): array
    {
        return $this->makeValidator($input, $this->getValidationRules())->validate();
    }

    protected function makeDynamicRules(): array
    {
        if ($this->fieldDefinition->type == PublicationFieldTypes::Media) {
            $mediaFiles = PublicationService::getMediaForType($this->publicationType);
            $valueList = $mediaFiles->implode(',');

            return ["in:$valueList"];
        }

        if ($this->fieldDefinition->type == PublicationFieldTypes::Tag) {
            $tagValues = PublicationService::getValuesForTagName($this->publicationType->getIdentifier()) ?? collect([]);
            $valueList = $tagValues->implode(',');

            return ["in:$valueList"];
        }

        return [];
    }

    protected function makeValidator(mixed $input, array $rules): Validator
    {
        return validator(
            [$this->fieldDefinition->name => $input],
            [$this->fieldDefinition->name => $rules]
        );
    }
}
