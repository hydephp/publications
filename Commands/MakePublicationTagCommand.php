<?php

declare(strict_types=1);

namespace Hyde\Publications\Commands;

use Hyde\Publications\Commands\Helpers\InputStreamHandler;
use Hyde\Publications\Models\PublicationTags;
use Hyde\Publications\PublicationService;

use function implode;

use LaravelZero\Framework\Commands\Command;
use RuntimeException;

use function sprintf;

/**
 * Hyde Command to create a new publication type.
 *
 * @see \Hyde\Publications\Testing\Feature\MakePublicationTagCommandTest
 */
class MakePublicationTagCommand extends ValidatingCommand
{
    /** @var string */
    protected $signature = 'make:publicationTag {tagName? : The name of the tag to create}';

    /** @var string */
    protected $description = 'Create a new publication type tag definition';

    protected array $tags;
    protected string $tagName;

    public function safeHandle(): int
    {
        $this->title('Creating a new Publication Type Tag!');

        $this->getTagName();

        $this->validateTagName();

        $this->collectTags();

        $this->printSelectionInformation();

        $this->saveTagsToDisk();

        return Command::SUCCESS;
    }

    protected function getTagName(): void
    {
        $this->tagName = $this->getTagNameFromArgument($this->argument('tagName'))
            ?? $this->askWithValidation('name', 'Tag name', ['required', 'string']);
    }

    protected function getTagNameFromArgument(?string $value): ?string
    {
        if ($value) {
            $this->infoComment("Using tag name [$value] from command line argument");
            $this->newLine();

            return $value;
        }

        return null;
    }

    protected function validateTagName(): void
    {
        if (PublicationService::getAllTags()->has($this->tagName)) {
            throw new RuntimeException("Tag [$this->tagName] already exists");
        }
    }

    protected function collectTags(): void
    {
        $this->info(sprintf('Enter the tag values: (%s)', InputStreamHandler::terminationMessage()));
        $this->tags = [$this->tagName => InputStreamHandler::call()];
    }

    protected function printSelectionInformation(): void
    {
        $this->line('Adding the following tags:');
        foreach ($this->tags as $tag => $values) {
            $this->line(sprintf('  <comment>%s</comment>: %s', $tag, implode(', ', $values)));
        }
        $this->newLine();
    }

    protected function saveTagsToDisk(): void
    {
        $this->infoComment(sprintf('Saving tag data to [%s]',
            \Hyde\Console\Concerns\Command::fileLink('tags.yml')
        ));

        (new PublicationTags)->addTagGroups($this->tags)->save();
    }
}
