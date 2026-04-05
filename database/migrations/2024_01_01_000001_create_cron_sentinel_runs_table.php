<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cron_sentinel_runs', function (Blueprint $table) {
            $table->id();
            $table->string('command');
            $table->timestamp('ran_at');
            $table->integer('exit_code')->default(0);
            $table->integer('duration_ms')->default(0);
            $table->text('output')->nullable();
            $table->timestamps();

            $table->index(['command', 'ran_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_sentinel_runs');
    }
};
