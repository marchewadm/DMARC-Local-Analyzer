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
        Schema::create('dmarc_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->string('provider_name');
            $table->string('provider_email');
            $table->string('provider_extra_contact')->nullable();

            $table->dateTime('report_start');
            $table->dateTime('report_end');

            $table->enum('dkim_alignment', ['relaxed', 'strict']);
            $table->enum('spf_alignment', ['relaxed', 'strict']);
            $table->enum('policy', ['none', 'quarantine', 'reject']);
            $table->enum('sub_domain_policy', ['none', 'quarantine', 'reject']);
            $table->unsignedTinyInteger('percentage');

            $table->string('domain');
            $table->string('report_id');

            $table->timestamps();
        });

        // add CHECK CONSTRAINT to make sure that "percentage" column has values between 0 and 100
        DB::statement('ALTER TABLE dmarc_reports ADD CONSTRAINT check_percentages CHECK (percentage >= 0 AND percentage <= 100)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dmarc_reports');
    }
};
