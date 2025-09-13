<?php

namespace Droath\PrismTransformer\Commands;

use Illuminate\Console\Command;

class PrismTransformerCommand extends Command
{
    public $signature = 'prism-transformer';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
