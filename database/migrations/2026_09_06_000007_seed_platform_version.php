<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key' => 'app_version',
            'value' => (string) config('platform.version', '0.10.0-dev'),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        throw new LogicException('Platform version history is forward-only.');
    }
};
