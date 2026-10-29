<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Replaces Laravel's default users migration (delete 0001_01_01_000000_create_users_table.php).
return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 16)->unique();        // one phone => one user => one role
            $table->string('role', 16);
            $table->string('status', 24)->default('needs_profile');
            $table->string('status_before_suspension', 24)->nullable();
            $table->string('full_name')->nullable();
            $table->string('avatar_key')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->index(['role', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
