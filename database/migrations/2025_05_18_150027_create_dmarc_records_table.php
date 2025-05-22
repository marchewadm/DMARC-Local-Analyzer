<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dmarc_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dmarc_report_id')->constrained()->onDelete('cascade');

            $table->ipAddress('source_ip');
            $table->unsignedSmallInteger('count');
            $table->enum('disposition', ['none', 'quarantine', 'reject']);
            $table->enum('dkim_result', ['fail', 'pass']);
            $table->enum('spf_result', ['fail', 'pass']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dmarc_records');
    }
};
