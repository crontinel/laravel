<?php

declare(strict_types=1);

namespace Crontinel\Commands;

use Crontinel\Services\SaasReporter;
use Crontinel\Services\ScheduleCatalog;
use Illuminate\Console\Command;

class ScheduleCommand extends Command
{
    protected $signature = 'crontinel:schedule
                            {--minimum= : processed_records minimum that means each listed run counted}';

    protected $description = 'Name the Laravel schedule in Crontinel and ask for the count that means a run counted';

    public function handle(ScheduleCatalog $catalog, SaasReporter $reporter): int
    {
        $tasks = $catalog->tasks();
        if ($tasks === []) {
            $this->warn('No scheduled tasks were found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Job', 'Command', 'Schedule'],
            array_map(fn (array $task) => [
                $task['job_name'] ?? $task['command'],
                $task['command'],
                $task['expression'] ?? '',
            ], $tasks),
        );

        $minimum = $this->option('minimum');
        if (($minimum === null || $minimum === '') && $this->input->isInteractive()) {
            $minimum = $this->ask('What number means the run counted? This is the processed_records minimum. Leave empty to skip.');
        }
        if ($minimum !== null && $minimum !== '') {
            if (! is_numeric($minimum)) {
                $this->error('The count must be a number.');

                return self::FAILURE;
            }
            $number = (float) $minimum;
            $counted = floor($number) === $number ? (int) $number : $number;
            foreach ($tasks as &$task) {
                $task['counted_minimum'] = $counted;
            }
            unset($task);
        } else {
            $this->line('No count was given. A completed process is not proof the run counted.');
        }

        if (empty(config('crontinel.saas_key'))) {
            $this->warn('CRONTINEL_API_KEY is not set. The schedule was listed and nothing was sent.');

            return self::FAILURE;
        }

        try {
            $reporter->reportSchedule($tasks);
        } catch (\Throwable $e) {
            $this->error('Crontinel could not store the named schedule.');

            return self::FAILURE;
        }

        $this->info('Named '.count($tasks).' scheduled task'.(count($tasks) === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
