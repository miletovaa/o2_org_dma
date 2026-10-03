<?php

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
        Schema::create('reference_files', function (Blueprint $table) {
            $table->id();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('reference_file_sample', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reference_file_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sample_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['reference_file_id', 'sample_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reference_file_sample');
        Schema::dropIfExists('reference_files');
    }
};
