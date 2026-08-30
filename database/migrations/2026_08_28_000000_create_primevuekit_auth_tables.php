<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Las tres primeras columnas usan los mismos nombres que laravel/fortify, así que
        // cambiar de estrategia no exige otra migración. Se comprueba una por una porque
        // `fortify:install` publica su propia migración y el orden de ejecución no es fijo.
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'two_factor_secret')) {
                $table->text('two_factor_secret')->nullable();
            }

            if (! Schema::hasColumn('users', 'two_factor_recovery_codes')) {
                $table->text('two_factor_recovery_codes')->nullable();
            }

            if (! Schema::hasColumn('users', 'two_factor_confirmed_at')) {
                $table->timestamp('two_factor_confirmed_at')->nullable();
            }

            // Contador de 30 s del último código TOTP aceptado: impide reutilizarlo
            // dentro de la ventana. Fortify no lo guarda, esta columna es del kit.
            if (! Schema::hasColumn('users', 'two_factor_last_used_counter')) {
                $table->unsignedBigInteger('two_factor_last_used_counter')->nullable();
            }

            if (! Schema::hasColumn('users', 'email_otp_enabled')) {
                $table->boolean('email_otp_enabled')->default(false);
            }
        });

        Schema::create('email_otp_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_otp_codes');

        // Sólo se eliminan las columnas propias del kit. Las tres de dos factores pueden
        // haberlas creado la migración de Fortify, y borrarlas destruiría datos ajenos.
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'two_factor_last_used_counter')) {
                $table->dropColumn('two_factor_last_used_counter');
            }

            if (Schema::hasColumn('users', 'email_otp_enabled')) {
                $table->dropColumn('email_otp_enabled');
            }
        });
    }
};
