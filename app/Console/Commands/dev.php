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
        $peleton = config('projectGroup.project_groups.peleton');
        $rndKrd = config('projectGroup.project_groups.krd_rnd');
        dd(in_array('volkswagen-rnd.ru', $rndKrd));

        $baseController = new BaseController();
        $baseController->uploadFile();
    }
}
