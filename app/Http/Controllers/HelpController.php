<?php

namespace App\Http\Controllers;

use App\Models\Central\CentralSetting;
use Illuminate\View\View;

/** Help: how the daily work is done, the videos of the company, and the tour of the screen. */
class HelpController extends Controller
{
    /** title => what to do, step by step */
    public const TOPICS = [
        'Receive money from a customer' => ['Press "Money In" in the row under the top bar.', 'Choose the customer and the cash, bank or saraf account the money went into.', 'Write the amount, and the payback date if there is one, and save. The receipt can be printed or sent on WhatsApp.'],
        'Pay money to a customer or a supplier' => ['Press "Money Out".', 'Choose the account and where the money is paid from.', 'Write the amount and save. The balance of the account changes at once.'],
        'Exchange one currency for another' => ['Press "Exchange".', 'Choose the account the money leaves and the account it arrives in.', 'Write the amount given. Leave the amount received empty to use the usual rate, or write it as it was. The profit or loss is counted by the system.'],
        'See what a customer has with you' => ['Open his category under Accounts and press "Statement" on his row.', 'Choose the currency and the dates.', 'Print it, save it as PDF, or make a private link he can open himself.'],
        'Add a new kind of account' => ['Open Configuration, Account Categories.', 'Add the category, for example "Partners".', 'Its page appears under Accounts by itself.'],
        'Close the day' => ['Open Currency Position to see the money in every currency.', 'Count the cash and write it under Daily Cash Count, or hand your box over under Cash Boxes & Handover.', 'Read the evening summary, or have it sent to you on WhatsApp under Alerts & Summary.'],
        'Keep a copy of your books' => ['Open Administration, Backup & Restore.', 'Press "Back up now" and download the file.', 'The system also saves a copy every night by itself.'],
    ];

    public function index(): View
    {
        $videos = [];
        foreach (preg_split('/\R/', (string) CentralSetting::read('help_videos')) as $line) {
            [$title, $url] = array_map('trim', explode('|', $line, 2) + [1 => '']);
            if ($title !== '' && preg_match('#^https://#i', $url)) {
                $videos[] = ['title' => $title, 'url' => $url];
            }
        }

        return view('help.index', ['topics' => self::TOPICS, 'videos' => $videos]);
    }

    /** The steps of the tour of the screen: where to look, a title and a sentence. */
    public static function tour(): array
    {
        return [
            ['el' => '#sidebarMenu', 'title' => __('The menu'), 'text' => __('Every part of the system is here: accounts, transactions, reports and settings. Each account category has its own page under Accounts.')],
            ['el' => '.quickbar', 'title' => __('Daily work'), 'text' => __('The papers you write every day open from this row: money in, money out, exchange and expense.')],
            ['el' => '.topbar-search', 'title' => __('Search'), 'text' => __('Find an account, a paper or a page by typing a few letters.')],
            ['el' => '.topbar-icon.position-relative', 'title' => __('Notifications'), 'text' => __('What the system wants you to know: unusual activity, budgets, rates you were waiting for.')],
            ['el' => '.topbar-user', 'title' => __('Your menu'), 'text' => __('Your password, two-step sign-in, the licence, support and this help are here.')],
        ];
    }
}
