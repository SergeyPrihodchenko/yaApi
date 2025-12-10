<?php

namespace App\Console\Commands;

use App\Http\Controllers\api\BaseController;
use Illuminate\Console\Command;

class DownloadingLogsInYaDisk extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:downloading-logs-in-ya-disk';

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
        (new BaseController)->uploadFile();
    }
}
