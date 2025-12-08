<?php

namespace App\Console\Commands;

use App\Http\Controllers\api\BaseController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class dev extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:dev';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $base = new BaseController();
        $base->downloadFile();

    }
}
