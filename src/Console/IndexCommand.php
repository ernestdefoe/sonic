<?php

namespace Ernestdefoe\Sonic\Console;

use Ernestdefoe\Sonic\Job\RebuildJob;
use Ernestdefoe\Sonic\Sonic;
use Flarum\Console\AbstractCommand;
use Illuminate\Contracts\Container\Container;
use Symfony\Component\Console\Input\InputOption;

class IndexCommand extends AbstractCommand
{
    public function __construct(
        protected Sonic $sonic,
        protected Container $container
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('sonic:index')
            ->setDescription('Rebuild (or flush) this forum\'s Sonic search indexes: discussions, posts and users.')
            ->addOption('flush', null, InputOption::VALUE_NONE, 'Empty the indexes instead of rebuilding them.');
    }

    protected function fire(): int
    {
        if (! $this->sonic->configured()) {
            $this->error('Sonic is not configured. Enter the host under Admin → Sonic Search.');

            return 1;
        }

        $flush = (bool) $this->input->getOption('flush');

        foreach (RebuildJob::INDEXERS as $class) {
            $indexer = $this->container->make($class);
            $this->info(($flush ? 'Flushing ' : 'Rebuilding ').$indexer::index().'…');
            $flush ? $indexer->flush() : $indexer->build();
        }

        if (! $flush) {
            $this->sonic->consolidate();
        }

        $this->info('Done.');

        return 0;
    }
}
