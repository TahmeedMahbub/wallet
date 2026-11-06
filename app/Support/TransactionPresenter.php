<?php

namespace App\Support;

use App\Enums\DueDirection;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * How an entry is shown in lists and in the entry-details sheet, following the
 * Figma "Txn Row" variants: Expense, Income, Move (transfers, savings, balance
 * changes) and People (lend, borrow, payments).
 */
final class TransactionPresenter
{
    /**
     * @return array{kind: string, label: string, title: string, meta: string, amount: string, amount_class: string, icon: string, icon_bg: string, icon_ink: string, account: string, balance: string|null}
     */
    public static function present(Transaction $entry, User $user, bool $compact = false): array
    {
        $type = $entry->type;
        $when = $compact ? CarbonImmutable::instance($entry->occurred_at)->setTimezone($user->timezone)->format('g:i A') : self::when($entry->occurred_at, $user);
        $account = $entry->account?->name ?? '';

        [$kind, $label] = match ($type) {
            TransactionType::Expense => ['expense', 'Expense'],
            TransactionType::Income => ['income', 'Income'],
            TransactionType::Transfer => ['move', 'Transfer'],
            TransactionType::BalanceAdjustment => ['move', 'Balance set'],
            TransactionType::SavingDeposit => ['move', 'Savings'],
            TransactionType::SavingWithdrawal => ['move', 'Savings withdrawal'],
            TransactionType::Lend => ['people', 'Lend'],
            TransactionType::Borrow => ['people', 'Borrow'],
            TransactionType::DuePayment => ['people', $entry->due?->direction === DueDirection::Lent ? 'Return received' : 'Repayment'],
        };

        $title = match ($type) {
            TransactionType::Expense => $entry->reason ?: ($entry->category?->name ?? 'Expense'),
            TransactionType::Income => $entry->incomeSource?->name ?? 'Income',
            TransactionType::Transfer => ($entry->account?->name ?? '').' → '.($entry->toAccount?->name ?? ''),
            TransactionType::BalanceAdjustment => $entry->note ?: 'Balance set',
            TransactionType::SavingDeposit => $entry->note ?: 'Added to savings',
            TransactionType::SavingWithdrawal => 'Withdrawn from savings',
            TransactionType::Lend => 'Lent to '.($entry->due?->person ?? ''),
            TransactionType::Borrow => 'Borrowed from '.($entry->due?->person ?? ''),
            TransactionType::DuePayment => ($entry->due?->direction === DueDirection::Lent ? 'Returned by ' : 'Paid to ').($entry->due?->person ?? ''),
        };

        $meta = match ($type) {
            TransactionType::Expense => collect([$entry->category?->name, $entry->area, $when])->filter()->implode(' · '),
            TransactionType::Income => collect([$when, $entry->note])->filter()->implode(' · '),
            TransactionType::Transfer => 'Transfer · '.$when,
            TransactionType::Lend, TransactionType::Borrow => collect([$when, $entry->note ?: null])->filter()->implode(' · '),
            default => $when,
        };

        $signed = self::signedAmount($entry);

        return [
            'kind' => $kind,
            'label' => $label,
            'title' => $title,
            'meta' => $meta,
            'amount' => $type === TransactionType::Transfer ? Money::format($entry->amount) : Money::format($signed, withSign: $signed > 0),
            'amount_class' => match ($kind) {
                'income' => 'text-green-700',
                'move' => 'text-blue-700',
                'people' => 'text-orange-900',
                default => 'text-grey-900',
            },
            'icon' => match ($kind) {
                'income' => 'arrow-in',
                'move' => 'repeat',
                'people' => 'dues',
                default => 'arrow-out',
            },
            'icon_bg' => match ($kind) {
                'income' => 'bg-green-50',
                'move' => 'bg-blue-50',
                'people' => 'bg-yellow-50',
                default => 'bg-red-50',
            },
            'icon_ink' => match ($kind) {
                'income' => 'text-green-700',
                'move' => 'text-blue-700',
                'people' => 'text-orange-900',
                default => 'text-red-700',
            },
            'account' => $account,
            'balance' => $entry->getAttribute('balance_after') === null ? null : $account.' · '.Money::format((int) $entry->getAttribute('balance_after')),
        ];
    }

    /**
     * The effect on the entry's own account: negative = money out.
     */
    public static function signedAmount(Transaction $entry): int
    {
        return match ($entry->type) {
            TransactionType::Expense, TransactionType::Transfer, TransactionType::SavingDeposit, TransactionType::Lend => -$entry->amount,
            TransactionType::Income, TransactionType::SavingWithdrawal, TransactionType::Borrow => $entry->amount,
            TransactionType::DuePayment => $entry->due?->direction === DueDirection::Lent ? $entry->amount : -$entry->amount,
            TransactionType::BalanceAdjustment => (int) $entry->postings->sum('delta'),
        };
    }

    /**
     * "Today, 1:30 PM", "Yesterday, 9:07 PM" or "Oct 15, 10:00 AM" in the user's timezone.
     */
    public static function when(CarbonImmutable|CarbonInterface $moment, User $user): string
    {
        $local = CarbonImmutable::instance($moment)->setTimezone($user->timezone);
        $today = Months::today($user);
        $time = $local->format('g:i A');

        return match (true) {
            $local->isSameDay($today) => 'Today, '.$time,
            $local->isSameDay($today->subDay()) => 'Yesterday, '.$time,
            default => $local->format('M j').', '.$time,
        };
    }
}
