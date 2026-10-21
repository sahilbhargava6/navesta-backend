<?php

namespace Database\Seeders;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::first();

        $posts = [
            [
                'title' => 'The Complete Guide to Credit Card Balance Transfers in India (2026)',
                'slug' => 'guide-to-credit-card-balance-transfers-india',
                'excerpt' => 'How Indian borrowers can slash credit card interest from 42% APR down to 10.25% through institutional bank-to-bank takeovers.',
                'meta_description' => 'A comprehensive guide explaining balance transfers, processing fee math, eligibility, and how to eliminate revolving card debt.',
                'status' => PostStatus::Published,
                'published_at' => now()->subDays(5),
                'body' => <<<MARKDOWN
# The Complete Guide to Credit Card Balance Transfers in India (2026)

Revolving credit card debt in India is amongst the most expensive unsecured debt in the world, often compounding at **42% to 48% APR**. If you maintain an outstanding balance of ₹2,00,000 and pay only the 5% minimum payment, you will pay over ₹3,40,000 in interest alone and stay trapped for more than 12 years.

## What is an Institutional Balance Transfer?

A Balance Transfer (BT) allows an institutional partner bank or NBFC (such as SBI, HDFC, or Tata Capital) to pay off your high-cost credit card facility directly. In exchange, the balance is converted into a structured term facility with fixed monthly EMIs at **10.25% to 12.5% p.a.**

### Why Takeover Beats Minimum Dues:
1. **Dramatic Interest Reduction:** Rates drop from 42% APR down to ~10.5%.
2. **Fixed Elimination Horizon:** Repaid in 12 to 36 structured months.
3. **Credit Score Protection:** Drops credit card utilization below 30%, giving an immediate CIBIL score boost.

## Understanding the Math
When transferring ₹3,00,000:
- **Card Revolving Cost:** ~₹1,26,000 annual interest burden.
- **Transferred 10.25% Facility:** Monthly EMI of ~₹9,715 with net interest savings exceeding ₹1,80,000 over 3 years.

## Eligibility Criteria
To qualify with partner lenders, you generally require:
- A CIBIL bureau score of **700+**
- Minimum 6 months of steady employment
- Active KYC documents (PAN & Aadhaar)
MARKDOWN,
            ],
            [
                'title' => 'Debt Avalanche vs Debt Snowball: Which Formula Saves More Money?',
                'slug' => 'debt-avalanche-vs-debt-snowball-mathematical-comparison',
                'excerpt' => 'A mathematical breakdown of Avalanche (highest interest first) versus Snowball (smallest balance first) for Indian debt portfolios.',
                'meta_description' => 'Compare the financial savings of the Debt Avalanche method against the psychological momentum of the Debt Snowball strategy.',
                'status' => PostStatus::Published,
                'published_at' => now()->subDays(3),
                'body' => <<<MARKDOWN
# Debt Avalanche vs Debt Snowball: Which Formula Saves More Money?

When managing multiple obligations—such as two credit cards, a personal loan, and a car EMI—deciding which balance to accelerate makes a massive financial difference.

## 1. The Debt Avalanche Method (Highest APR First)
In the Avalanche strategy, you make minimum payments across all facilities, but allocate every rupee of surplus cash to the account with the **highest interest rate**.

- **Best for:** Borrowers focused purely on mathematical optimization and paying the least total interest.
- **Average Savings:** Saves 15% to 35% more interest than random repayments.

## 2. The Debt Snowball Method (Smallest Balance First)
In the Snowball strategy, surplus funds target the debt with the **smallest outstanding rupee balance**, regardless of APR.

- **Best for:** Borrowers who need quick psychological wins and want to eliminate accounts quickly.

## Which Should You Choose?
At Navesta, our algorithmic engine dynamically models both methods side by side so you can compare exact payoff dates and net rupee interest savings before making a decision.
MARKDOWN,
            ],
            [
                'title' => 'How Foreclosing High-Interest Loans Can Boost Your CIBIL Score',
                'slug' => 'how-foreclosing-loans-boosts-cibil-score',
                'excerpt' => 'Understanding bureau credit mix, credit utilization ratio, and how debt restructuring protects your long-term borrowing profile.',
                'meta_description' => 'Learn how loan consolidation and balance takeover positively impact your credit score and bureau health.',
                'status' => PostStatus::Published,
                'published_at' => now()->subDay(),
                'body' => <<<MARKDOWN
# How Foreclosing High-Interest Loans Can Boost Your CIBIL Score

Many borrowers believe that having multiple loans is harmless as long as EMIs are paid on time. However, credit rating bureaus like TransUnion CIBIL, Experian, and CRIF High Mark evaluate more than just repayment history.

## Factors Impacting Your CIBIL Score:
1. **Repayment History (35%):** Timely clearance of dues.
2. **Credit Utilization Ratio (30%):** Using more than 30% of card limits triggers immediate penalty marks.
3. **Credit Mix (10%):** Having excessive unsecured debt (cards and personal loans) signals financial vulnerability.

## The Takeover Advantage
Consolidating three small loans and two credit cards into one structured institutional facility eliminates multiple concurrent liabilities and drastically reduces your revolving utilization ratio, leading to a noticeable credit score surge within 90 days.
MARKDOWN,
            ],
        ];

        foreach ($posts as $postData) {
            Post::firstOrCreate(
                ['slug' => $postData['slug']],
                array_merge($postData, ['author_id' => $author?->id])
            );
        }
    }
}
