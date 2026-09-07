<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_records', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->string('code', 40)->unique();
            $table->text('url')->nullable();
            $table->text('description')->nullable();
            $table->boolean('status')->default(true);
            $table->decimal('balance', 12, 2)->default(0);
            $table->timestamps(6);
        });

        Schema::create('demo_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('demo_record_id')->nullable()->index();
            $table->string('action', 40);
            $table->text('message');
            $table->timestamps();
        });

        Schema::create('demo_states', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_logs');
        Schema::dropIfExists('demo_records');
        Schema::dropIfExists('demo_states');
    }
};
