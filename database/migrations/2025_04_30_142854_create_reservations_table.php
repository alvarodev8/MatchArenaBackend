<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('pitch_id')->constrained('pitches')->onDelete('cascade');
            $table->timestamp('start_at')->index();
            $table->integer('duration');
            $table->decimal('price', 8, 2);
            $table->enum('status', ['pending', 'confirmed', 'cancelled'])->default('pending');
            $table->enum('payment_status', ['pending', 'completed', 'failed', 'refunded'])->nullable();
            $table->string('payment_method')->nullable();
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->text('cancellation_reason')->nullable();
            $table->dateTime('cancellation_date')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('reservations');
    }
};
