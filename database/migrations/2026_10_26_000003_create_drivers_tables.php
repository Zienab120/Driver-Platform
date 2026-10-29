<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Step 1 (age is derived from birth_date, never stored)
            $table->char('national_id', 14)->nullable()->unique();
            $table->date('birth_date')->nullable();
            $table->string('gender', 8)->nullable();
            $table->unsignedBigInteger('district_id')->nullable();

            // Step 2
            $table->string('plate_numbers', 4)->nullable();
            $table->string('plate_letters', 12)->nullable(); // stored without spaces
            $table->string('model', 80)->nullable();
            $table->string('color', 16)->nullable();

            // Progress + review
            $table->timestamp('step1_completed_at')->nullable();
            $table->timestamp('step2_completed_at')->nullable();
            $table->timestamp('step3_completed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->unique(['plate_numbers', 'plate_letters']);
        });

        Schema::create('driver_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('file_key');
            $table->string('status', 16)->default('pending'); // pending|approved|rejected (per-document review)
            $table->text('rejection_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['driver_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_documents');
        Schema::dropIfExists('drivers');
    }
};
