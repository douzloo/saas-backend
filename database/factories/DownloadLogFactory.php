<?php

namespace Database\Factories;

use App\Models\Download;
use App\Models\DownloadLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DownloadLog>
 */
class DownloadLogFactory extends Factory
{
    protected $model = DownloadLog::class;

    public function definition(): array
    {
        return [
            'download_id' => Download::factory(),
            'user_id' => User::factory(),
            'license_id' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
