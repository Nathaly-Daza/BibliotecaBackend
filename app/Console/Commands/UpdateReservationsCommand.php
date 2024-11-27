<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateReservationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:reservations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the status of reservations that are out of date.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public static function handle(){
        $date = date('Y-m-d');
        DB::table('reservations AS res')
            ->where('res.res_date', '<', $date)
            ->update(['res_status' => 0]);
        return 0;
    }

}
