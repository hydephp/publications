<?php

declare(strict_types=1);

namespace Hyde\Publications\Commands;

use Closure;
use Hyde\Hyde;
use Hyde\Publications\Actions\CreatesNewPublicationPage;
use Hyde\Publications\Commands\Helpers\InputStreamHandler;
use Hyde\Publications\Models\PublicationFieldDefinition;
use Hyde\Publications\Models\PublicationFieldValue;
use Hyde\Publications\Models\PublicationType;
use Hyde\Publications\PublicationFieldTypes;
use Hyde\Publications\PublicationService;
use Illuminate\Support\Collection;
use function implode;
use function in_array;
use InvalidArgumentException;
use LaravelZero\Framework\Commands\Command;
use function str_starts_with;

/**
 * Hyde Command to create a new publication for a given publication type.
 *
 * @see \Hyde\Publications\Actions\CreatesNewPublicationPage
 * @see \Hyde\Publications\Testing\Feature\MakePublicationCommandTest
 */
class MakePublicationCommand extends ValidatingCommand
{
    /** @var string */
    protected $signature = 'make:publication
		{publicationType? : The name of the publication type to create a publication for}
        {--force : Should the generated file overwrite existing publications with the same filename?}';

    /** @var string */
    protected $description = 'Create a new publication item';

    protected PublicationType $publicationType;

    /** @var \Illuminate\Support\Collection<string, \Hyde\Publications\Models\PublicationType> */
    protected Collection $fieldData;

    public function safeHandle(): int
    {
        $this->title('Creating a new publication!');

        $this->publicationType = $this->getPublicationTypeSelection();
        $this->fieldData = new Collection();

        $this->collectFieldData();

        $creator = new CreatesNewPublicationPage($this->publicationType, $this->fieldData, (bool) $this->option('force'));
        if ($creator->hasFileConflict()) {
            $this->error('Error: A publication already exists with the same canonical field value');
            if ($this->confirm('Do you wish to overwrite the existing file?')) {
                $creator->force();
            } else {
                $this->info('Exiting without overwriting existing publication file!');

                return ValidatingCommand::USER_EXIT;
            }
        }
        $creator->create();

        $this->infoComment("All done! Created file [{$creator->getOutputPath()}]");

        return Command::SUCCESS;
    }

    protected function getPublicationTypeSelection(): PublicationType
    {
        $publicationTypes = PublicationService::getPublicationTypes();
        if ($this->argument('publicationType')) {
            $publicationTypeSelection = $this->argument('publicationType');
        } else {
            if ($publicationTypes->isEmpty()) {
                throw new InvalidArgumentException('Unable to locate any publication types. Did you create any?');
            }

            $publicationTypeSelection = $this->choice(
                'Which publication type would you like to create a publication item for?',
                $publicationTypes->keys()->toArray()
            );
        }

        if ($publicationTypes->has($publicationTypeSelection)) {
            $this->line("<info>Creating a new publication of type</info> [<comment>$publicationTypeSelection</comment>]");

            return $publicationTypes->get($publicationTypeSelection);
        }

        throw new InvalidArgumentException("Unable to locate publication type [$publicationTypeSelection]");
    }

    protected function collectFieldData(): void
    {
        $this->newLine();
        $this->info('Now please enter the field data:');

        /** @var \Hyde\Publications\Models\PublicationFieldDefinition $field */
        foreach ($this->publicationType->getFields() as $field) {
            if (str_starts_with($field->name, '__')) {
                continue;
            }

            $this->newLine();
            $fieldInput = $this->captureFieldInput($field);
            if (empty($fieldInput)) {
                $this->line("<fg=gray> > Skipping field $field->name</>");
            } else {
                $this->fieldData->put($field->name, $fieldInput);
            }
        }

        $this->newLine();
    }

    protected function captureFieldInput(PublicationFieldDefinition $field): ?PublicationFieldValue
    {
        return match ($field->type) {
            PublicationFieldTypes::Text => $this->captureTextFieldInput($field),
            PublicationFieldTypes::Array => $this->captureArrayFieldInput($field),
            PublicationFieldTypes::Media => $this->captureMediaFieldInput($field),
            PublicationFieldTypes::Tag => $this->captureTagFieldInput($field),
            default => $this->captureOtherFieldInput($field),
        };
    }

    protected function captureTextFieldInput(PublicationFieldDefinition $field): PublicationFieldValue
    {
        $this->infoComment(sprintf("Enter lines for field [$field->name] (%s)", InputStreamHandler::terminationMessage()));

        return new PublicationFieldValue(PublicationFieldTypes::Text, implode("\n", InputStreamHandler::call()));
    }

    protected function captureArrayFieldInput(PublicationFieldDefinition $field): PublicationFieldValue
    {
        $this->infoComment(sprintf("Enter values for field [$field->name] (%s)", InputStreamHandler::terminationMessage()));

        return new PublicationFieldValue(PublicationFieldTypes::Array, InputStreamHandler::call());
    }

    protected function captureMediaFieldInput(PublicationFieldDefinition $field): ?PublicationFieldValue
    {
        $this->infoComment("Select file for image field [$field->name]");

        $mediaFiles = PublicationService::getMediaForType($this->publicationType);
        if ($mediaFiles->isEmpty()) {
            return $this->handleEmptyOptionsCollection($field, 'media file',
                sprintf('No media files found in directory %s/%s/', Hyde::getMediaDirectory(),
                    $this->publicationType->getIdentifier()
                )
            );
        }

        return new PublicationFieldValue(PublicationFieldTypes::Media, $this->choice('Which file would you like to use?', $mediaFiles->toArray()));
    }

    protected function captureTagFieldInput(PublicationFieldDefinition $field): ?PublicationFieldValue
    {
        $tagGroup = $field->tagGroup ?? throw new InvalidArgumentException("Tag field '$field->name' is missing the 'tagGroup' property");
        $this->infoComment(/** @lang Text */ "Select a tag for field [$field->name] from the $tagGroup group");

        $options = PublicationService::getValuesForTagName($tagGroup);
        if ($options->isEmpty()) {
            return $this->handleEmptyOptionsCollection($field, 'tag', 'No tags for this publication type found in tags.yml');
        }

        $this->tip('You can enter multiple tags separated by commas');

        $choice = $this->reloadableChoice($this->getReloadableTagValuesArrayClosure($tagGroup),
            'Which tag would you like to use?',
            'Reload tags.yml',
            true
        );

        return new PublicationFieldValue(PublicationFieldTypes::Tag, $choice);
    }

    protected function captureOtherFieldInput(PublicationFieldDefinition $field): ?PublicationFieldValue
    {
        $selection = $this->askForFieldData($field->name, $field->getRules());
        if (empty($selection)) {
            return null;
        }

        return new PublicationFieldValue($field->type, $selection);
    }

    protected function askForFieldData(string $name, array $rules): string
    {
        return $this->askWithValidation($name, "Enter data for field </>[<comment>$name</comment>]", $rules);
    }

    /** @return null */
    protected function handleEmptyOptionsCollection(PublicationFieldDefinition $field, string $type, string $message)
    {
        if (in_array('required', $field->rules)) {
            throw new InvalidArgumentException("Unable to create publication: $message");
        }

        $this->newLine();
        $this->warn("<fg=red>Warning:</> $message");
        if ($this->confirm('Would you like to skip this field?', true)) {
            return null;
        } else {
            throw new InvalidArgumentException("Unable to locate any {$type}s for this publication type");
        }
    }

    protected function tip(string $message): void
    {
        $this->line("<fg=bright-blue>Tip:</> $message");
    }

    /** @return Closure<array<string>> */
    protected function getReloadableTagValuesArrayClosure(string $tagGroup): Closure
    {
        return function () use ($tagGroup): array {
            return PublicationService::getValuesForTagName($tagGroup)->toArray();
        };
    }
}
