<?php

namespace App\Services;

use App\Enums\FineStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ActivityLog;
use App\Models\Fine;
use App\Models\FinePayment;
use App\Models\User;
use App\Support\Format;
use Illuminate\Support\Facades\DB;

class FineService
{
    /**
     * Mencatat pembayaran (penuh atau cicilan). Status menjadi "paid" saat lunas.
     *
     * @throws BusinessRuleException
     */
    public function pay(Fine $fine, User $actor, float|int|string $amount, string $method, ?string $notes = null): FinePayment
    {
        $amount = round((float) $amount, 2);

        return DB::transaction(function () use ($fine, $actor, $amount, $method, $notes) {
            $locked = Fine::query()->lockForUpdate()->findOrFail($fine->id);

            if ($locked->status !== FineStatus::Unpaid) {
                throw new BusinessRuleException('Denda ini sudah diselesaikan.');
            }

            $total = round((float) $locked->amount, 2);
            $remaining = round($total - (float) $locked->paid_amount, 2);

            if ($amount <= 0) {
                throw new BusinessRuleException('Nominal pembayaran harus lebih dari 0.');
            }

            if ($amount > $remaining) {
                throw new BusinessRuleException('Nominal melebihi sisa denda (' . Format::rupiah($remaining) . ').');
            }

            $payment = $locked->payments()->create([
                'type' => FinePayment::TYPE_PAYMENT,
                'amount' => $amount,
                'method' => $method,
                'paid_at' => now(),
                'received_by' => $actor->id,
                'notes' => $notes,
            ]);

            $paid = round((float) $locked->paid_amount + $amount, 2);
            $attributes = ['paid_amount' => $paid];

            if ($paid >= $total) {
                $attributes += [
                    'status' => FineStatus::Paid,
                    'settled_at' => now(),
                    'settled_by' => $actor->id,
                ];
            }

            $locked->update($attributes);

            ActivityLog::record(
                'fine.paid',
                'Menerima pembayaran denda ' . Format::rupiah($amount) . " (denda #{$locked->id})",
                $locked,
                ['amount' => $amount, 'method' => $method, 'settled' => $paid >= $total],
                $actor->id,
            );

            return $payment;
        });
    }

    /**
     * Membebaskan sisa denda. Pemanggil wajib memeriksa gate "waive-fines".
     *
     * @throws BusinessRuleException
     */
    public function waive(Fine $fine, User $actor, string $reason): FinePayment
    {
        return DB::transaction(function () use ($fine, $actor, $reason) {
            $locked = Fine::query()->lockForUpdate()->findOrFail($fine->id);

            if ($locked->status !== FineStatus::Unpaid) {
                throw new BusinessRuleException('Denda ini sudah diselesaikan.');
            }

            $remaining = round((float) $locked->amount - (float) $locked->paid_amount, 2);

            $payment = $locked->payments()->create([
                'type' => FinePayment::TYPE_WAIVER,
                'amount' => $remaining,
                'method' => null,
                'paid_at' => now(),
                'received_by' => $actor->id,
                'notes' => $reason,
            ]);

            $locked->update([
                'status' => FineStatus::Waived,
                'settled_at' => now(),
                'settled_by' => $actor->id,
                'notes' => $reason,
            ]);

            ActivityLog::record(
                'fine.waived',
                'Membebaskan denda ' . Format::rupiah($remaining) . " (denda #{$locked->id})",
                $locked,
                ['amount' => $remaining, 'reason' => $reason],
                $actor->id,
            );

            return $payment;
        });
    }
}
