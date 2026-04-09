<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('fedapay_transaction_id')->nullable()->after('description');
            $table->string('payment_status')->default('pending')->after('fedapay_transaction_id');
            $table->string('payment_method')->nullable()->after('payment_status');
            $table->string('phone')->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'fedapay_transaction_id',
                'payment_status',
                'payment_method',
                'phone',
            ]);
        });
    }
};
