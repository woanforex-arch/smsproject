<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Console\Command;

class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile
                            {--minutes=120 : Only check payments created more than N minutes ago}
                            {--limit=100 : Maximum payments to verify per run}';

    protected $description = 'Re-check pending Flutterwave payments and settle any that actually succeeded.';

    public function handle(PaymentService $payments): int
    {
        $cutoff = now()->subMinutes(max(1, (int) $this->option('minutes')));

        $pending = Payment::query()
            ->where('status', PaymentStatus::Pending->value)
            ->where('created_at', '<=', $cutoff)
            ->latest('id')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($pending->isEmpty()) {
            $this->info('No pending payments to reconcile.');

            return self::SUCCESS;
        }

        $confirmed = 0;
        $failed = 0;

        foreach ($pending as $payment) {
            $result = $payments->confirmByReference($payment, 'reconcile');

            if ($result['ok']) {
                $confirmed++;
                $this->line("  <info>✓</info> {$payment->tx_ref} confirmed");
            } elseif ($result['payment']?->status === PaymentStatus::Pending) {
                // Held for manual review (e.g. an amount mismatch).
                $this->line("  <comment>!</comment> {$payment->tx_ref} needs review: {$result['message']}");
            } else {
                $failed++;
                $this->line("  <fg=red>✗</> {$payment->tx_ref}: {$result['message']}");
            }
        }

        $this->info("Reconciled {$pending->count()}: {$confirmed} confirmed, {$failed} failed.");

        return self::SUCCESS;
    }
}