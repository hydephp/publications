<?php

declare(strict_types=1);

namespace Hyde\Publications\Commands;

use Hyde\Publications\Actions\SeedsPublicationFiles;
use Hyde\Publications\Models\PublicationType;
use Hyde\Publications\PublicationService;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use LaravelZero\Framework\Commands\Command;

/**
 * Hyde Command to seed publication files for a publication type.
 *
 * @see \Hyde\Publications\Actions\SeedsPublicationFiles
 * @see \Hyde\Publications\Testing\Feature\SeedPublicationCommandTest
 */
class SeedPublicationCommand extends ValidatingCommand
{
    /** @var string */
    protected $signature = 'seed:publications
		{publicationType? : The name of the publication type to create publications for}
        {number? : The number of publications to generate}';

    /** @var string */
    protected $description = 'Generate random publications for a publication type';

    public function safeHandle(): int
    {
        $this->title('Seeding new publications!');

        $publicationType = $this->getPublicationTypeSelection($this->getPublicationTypes());
        $number = (int) ($this->argument('number') ?? $this->askWithValidation(
            'number',
            'How many publications would you like to generate',
            ['required', 'integer', 'between:1,100000'], 1));

        if ($number >= 10000) {
            $this->warn('Warning: Generating a large number of publications may take a while. <fg=gray>Expected time: '.($number / 1000).' seconds.</>');
            if (! $this->confirm('Are you sure you want to continue?')) {
                return parent::USER_EXIT;
            }
        }

        $timeStart = microtime(true);
        $seeder = new SeedsPublicationFiles($publicationType, $number);
        $seeder->create();

        $ms = round((microtime(true) - $timeStart) * 1000);
        $each = round($ms / $number, 2);
        $this->info(sprintf("<comment>$number</comment> publication{$this->pluralize($number)} for <comment>$publicationType->name</comment> created! <fg=gray>Took {$ms}ms%s",
                ($number > 1) ? " ({$each}ms/each)</>" : ''));

        return Command::SUCCESS;
    }

    /**
     * @param  \Illuminate\Support\Collection<string, \Hyde\Publications\Models\PublicationType>  $publicationTypes
     * @return \Hyde\Publications\Models\PublicationType
     */
    protected function getPublicationTypeSelection(Collection $publicationTypes): PublicationType
    {
        $publicationType = $this->argument('publicationType') ?? $publicationTypes->keys()->get(
            (int) $this->choice(
                'Which publication type would you like to seed?',
                $publicationTypes->keys()->toArray()
            )
        );

        if ($publicationTypes->has($publicationType)) {
            if ($this->argument('number')) {
                $this->line("<info>Creating</info> [<comment>{$this->argument('number')}</comment>] <info>random publications for type</info> [<comment>$publicationType</comment>]");
            } else {
                $this->line("<info>Creating random publications for type</info> [<comment>$publicationType</comment>]");
            }

            return $publicationTypes->get($publicationType);
        }

        throw new InvalidArgumentException("Unable to locate publication type [$publicationType]");
    }

    /**
     * @return \Illuminate\Support\Collection<string, PublicationType>
     *
     * @throws \InvalidArgumentException
     */
    protected function getPublicationTypes(): Collection
    {
        $publicationTypes = PublicationService::getPublicationTypes();
        if ($publicationTypes->isEmpty()) {
            throw new InvalidArgumentException('Unable to locate any publication types. Did you create any?');
        }

        return $publicationTypes;
    }

    protected function pluralize(int $count): string
    {
        return ($count === 1) ? '' : 's';
    }
}
