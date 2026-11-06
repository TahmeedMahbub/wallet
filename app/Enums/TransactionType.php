<?php

namespace App\Enums;

enum TransactionType: string
{
    case Expense = 'expense';
    case Income = 'income';
    case Transfer = 'transfer';
    case BalanceAdjustment = 'balance_adjustment';
    case SavingDeposit = 'saving_deposit';
    case SavingWithdrawal = 'saving_withdrawal';
    case Lend = 'lend';
    case Borrow = 'borrow';
    case DuePayment = 'due_payment';

    /**
     * Only expense, income and transfer entries can be edited; the rest can only be deleted.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Expense, self::Income, self::Transfer], true);
    }
}
