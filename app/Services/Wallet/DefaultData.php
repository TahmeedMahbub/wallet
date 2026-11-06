<?php

namespace App\Services\Wallet;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Category;
use App\Models\IncomeSource;
use App\Models\SavingPlace;
use App\Models\SavingReason;
use App\Models\User;

/**
 * Starting lists for a new user, taken from the Figma pickers.
 */
class DefaultData
{
    public function __construct(private readonly UnitOfWork $unitOfWork) {}

    public function seed(User $user): void
    {
        $this->unitOfWork->run($user, function (): void {
            if (Category::query()->exists()) {
                return;
            }

            foreach (['Food & Groceries', 'Rent', 'Transport', 'Bills & Utilities', 'Shopping', 'Health', 'Family', 'Others'] as $position => $name) {
                Category::create(['name' => $name, 'position' => $position]);
            }

            foreach ([['Salary', true], ['Festival bonus', false], ['Salami', false], ['Gift money', false], ['Pocket money', false]] as $position => [$name, $isSalary]) {
                IncomeSource::create(['name' => $name, 'is_salary' => $isSalary, 'position' => $position]);
            }

            foreach (['Personal Things', 'Tour', 'Qurbani', 'Zakat', 'Hajj', 'Provident Fund', 'Our Home'] as $position => $name) {
                SavingReason::create(['name' => $name, 'position' => $position]);
            }

            foreach (['Bank DPS', 'bKash Savings', 'Piggy Bank', 'Investment'] as $position => $name) {
                SavingPlace::create(['name' => $name, 'position' => $position]);
            }

            foreach ([['Cash', AccountType::Cash], ['bKash', AccountType::MobileWallet], ['Bank', AccountType::Bank]] as [$name, $type]) {
                Account::create(['name' => $name, 'type' => $type]);
            }
        });
    }
}
