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
        Schema::create('dmarc_spf_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dmarc_record_id')->constrained()->onDelete('cascade');

            $table->string('domain');
            $table->enum('result', ['fail', 'pass']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dmarc_spf_results');
    }
};
