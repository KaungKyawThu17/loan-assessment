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
        Schema::create('loan_applications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();

            $table->decimal('amount', 12, 2);

            $table->unsignedTinyInteger('term_months');

            $table->decimal('interest_rate', 5, 2);

            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
                'cancelled',
                'disbursed',
                'closed',
            ])->default('pending');

            $table->date('application_date');

            $table->timestamp('approved_at')->nullable();

            $table->string('purpose', 255);

            $table->text('supporting_notes')->nullable();

            $table->foreignId('assigned_reviewer_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('decision_notes')->nullable();

            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_applications');
    }
};
