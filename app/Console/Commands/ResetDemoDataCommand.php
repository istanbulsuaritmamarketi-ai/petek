<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\DiningMenu;
use App\Models\LibraryFloor;

class ResetDemoDataCommand extends Command
{
    protected $signature = 'petek:reset-demo-data';
    protected $description = 'Reset fake demo ratings, deals, hashtags, library occupancy and dining notifications';

    public function handle(): int
    {
        $this->info('Resetting demo and sample data...');

        // 1. Reset Deals / Promos
        if (DB::getSchemaBuilder()->hasTable('deals')) {
            DB::table('deals')->truncate();
            $this->info('✓ Deals table emptied (all demo promotions removed).');
        }

        // 2. Reset Hashtags if table exists
        if (DB::getSchemaBuilder()->hasTable('hashtags')) {
            DB::table('hashtags')->truncate();
            $this->info('✓ Hashtags table emptied.');
        }

        // 3. Reset Dining Ratings & Menus
        if (DB::getSchemaBuilder()->hasTable('dining_ratings')) {
            DB::table('dining_ratings')->truncate();
        }
        if (DB::getSchemaBuilder()->hasTable('dining_menus')) {
            DiningMenu::query()->update([
                'average_rating' => 0.0,
                'rating_sum' => 0,
                'rating_count' => 0,
                'notification_sent' => false,
            ]);
            $this->info('✓ Dining ratings and notification flags reset to 0.');
        }

        // 4. Reset Zone Votes
        if (DB::getSchemaBuilder()->hasTable('zone_votes')) {
            DB::table('zone_votes')->truncate();
            $this->info('✓ Zone votes emptied.');
        }

        // 5. Reset Library Floors & Reports
        if (DB::getSchemaBuilder()->hasTable('library_reports')) {
            DB::table('library_reports')->truncate();
        }
        if (DB::getSchemaBuilder()->hasTable('library_floors')) {
            LibraryFloor::query()->update([
                'occupancy_rate' => 0,
                'report_count' => 0,
                'last_reported_at' => null,
            ]);
            $this->info('✓ Library floors occupancy and report counts reset to 0.');
        }

        // 6. Reset Old/Expired Vibes (24 saatten eski vibelar)
        if (DB::getSchemaBuilder()->hasTable('campus_vibes')) {
            $deletedVibes = DB::table('campus_vibes')
                ->where('expires_at', '<=', now())
                ->orWhere('created_at', '<=', now()->subHours(24))
                ->delete();
            $this->info("✓ Expired vibes cleaned ({$deletedVibes} removed).");
        }

        // 7. Invariant Protection Check
        $admin = DB::table('users')->where('id', 1)->first();
        $yigit = DB::table('users')->where('id', 3)->first();
        $this->info("✓ Admin accounts safe: Admin ID 1 ({$admin->email}), Yiğit Uzar ID 3 ({$yigit->email}).");

        $this->info('Demo data reset completed successfully!');
        return 0;
    }
}
