<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\AccountGroup;
use App\Models\AssistantQuestion;
use App\Models\Branch;
use App\Models\Central\CentralSetting;
use App\Models\Currency;
use App\Models\User;
use App\Models\Voucher;
use App\Support\Num;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * The assistant: the owner asks a question in his own language and is answered from the figures
 * of his business. A short summary of the books goes with the question to the Claude API; the
 * key of that service belongs to the company and is kept in the settings of the system.
 */
class Assistant
{
    public const MODEL = 'claude-haiku-5-5';

    public static function key(): ?string
    {
        $stored = CentralSetting::read('assistant_key');
        try {
            return filled($stored) ? Crypt::decryptString($stored) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function ready(): bool
    {
        return filled(self::key());
    }

    public static function limit(): int
    {
        return max(1, (int) (CentralSetting::read('assistant_daily_limit') ?: 40));
    }

    public static function ask(User $user, string $question): AssistantQuestion
    {
        $key = self::key();
        if (! $key) {
            throw new UserError(__('The assistant is not switched on yet.'));
        }
        if (AssistantQuestion::where('created_at', '>=', now()->startOfDay())->count() >= self::limit()) {
            throw new UserError(__('The questions of today are used up. Ask again tomorrow.'));
        }
        // the last two answers go along, so "and last month?" is understood
        $messages = [];
        foreach (AssistantQuestion::where('user_id', $user->id)->whereNotNull('answer')->where('created_at', '>=', now()->subHours(2))->orderByDesc('id')->limit(2)->get()->reverse() as $old) {
            $messages[] = ['role' => 'user', 'content' => $old->question];
            $messages[] = ['role' => 'assistant', 'content' => $old->answer];
        }
        $messages[] = ['role' => 'user', 'content' => "The figures of the business now:\n".self::snapshot()."\n\nThe question:\n".$question];

        try {
            $response = Http::withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'])->timeout(45)->post('https://api.anthropic.com/v1/messages', [
                'model' => CentralSetting::read('assistant_model') ?: self::MODEL, 'max_tokens' => 900, 'system' => self::rules(), 'messages' => $messages,
            ]);
        } catch (\Throwable $e) {
            report($e);
            throw new UserError(__('The assistant did not answer. Try again in a moment.'));
        }
        $text = trim(collect($response->json('content') ?? [])->where('type', 'text')->pluck('text')->implode("\n"));
        if (! $response->successful() || $text === '') {
            logger()->warning('Assistant: '.$response->status().' '.mb_substr($response->body(), 0, 300));
            throw new UserError(__('The assistant did not answer. Try again in a moment.'));
        }

        return AssistantQuestion::create(['user_id' => $user->id, 'question' => $question, 'answer' => $text,
            'tokens' => (int) $response->json('usage.input_tokens') + (int) $response->json('usage.output_tokens')]);
    }

    private static function rules(): string
    {
        return 'You are the assistant inside the accounting system "'.config('app.name').'" of a business in Afghanistan. '
            .'Answer the question of its owner from the figures given with it, and from nothing else. '
            .'Answer in the language of the question (Dari, Pashto or English), in a few plain sentences, with the numbers and their currency codes. '
            .'If the figures do not hold the answer, say so and name the report of the system where it is found (Profit & Loss, Balance Sheet, account statements, Daily Cash, Stock). '
            .'Never invent a number. You cannot record or change anything in the books. Use no headings and no tables.';
    }

    /** The books in a page of text: cash, rates, who owes and who is owed, income and expenses, what is due. */
    public static function snapshot(): string
    {
        $all = can_oversee();
        $work = function () use ($all) {
            $ids = Branch::reading();
            $base = Currency::base()->code;
            $out = ['Business: '.(setting('company_name') ?: tenant()?->name), 'Date: '.today_iso(), 'Base currency: '.$base.' (totals below are in it unless a code is written)'];

            foreach (Branch::whereIn('id', $ids)->orderBy('id')->get() as $branch) {
                $out[] = 'Cash and bank'.(count($ids) > 1 ? ' of branch '.$branch->name : '').' (balance now; in and out today):';
                foreach (Days::summary($branch, today_iso())['rows'] as $row) {
                    if (! (Num::isZero($row['closing']) && Num::isZero($row['in']) && Num::isZero($row['out']))) {
                        $out[] = '  '.$row['currency']->code.' '.Num::money($row['closing']).' (in '.Num::money($row['in']).', out '.Num::money($row['out']).')';
                    }
                }
            }
            $main = Branch::main();
            $rates = Currency::where('is_active', true)->where('is_base', false)->get()->map(fn (Currency $c) => '1 '.$c->code.' = '.(Rates::current($main, $c) ?? '?').' '.$base)->implode('; ');
            if ($rates !== '') {
                $out[] = 'Rates: '.$rates;
            }

            $parties = DB::table('account_balances as b')->join('accounts as a', 'a.id', '=', 'b.account_id')->join('parties as p', 'p.id', '=', 'a.party_id')
                ->join('account_groups as g', 'g.id', '=', 'p.account_group_id')->where('g.type', AccountGroup::PARTY)->whereIn('a.branch_id', $ids)
                ->groupBy('p.id', 'p.name', 'g.name')->selectRaw('p.name as name, g.name as category, SUM(b.base_balance) as owed')->get()
                ->map(fn ($row) => ['name' => $row->name.' ('.$row->category.')', 'owed' => Num::money($row->owed)])->reject(fn ($row) => Num::isZero($row['owed']));
            $owe = $parties->filter(fn ($row) => Num::isPositive($row['owed']))->sortByDesc(fn ($row) => (float) $row['owed'])->values();
            $owed = $parties->filter(fn ($row) => Num::isNegative($row['owed']))->sortBy(fn ($row) => (float) $row['owed'])->values();
            $out[] = 'People who owe the business: '.$owe->count().', together '.Num::money(Num::sum($owe->pluck('owed'))).'. The largest: '.($owe->take(15)->map(fn ($r) => $r['name'].' '.$r['owed'])->implode('; ') ?: 'none');
            $out[] = 'People the business owes: '.$owed->count().', together '.Num::money(Num::neg(Num::sum($owed->pluck('owed')))).'. The largest: '.($owed->take(15)->map(fn ($r) => $r['name'].' '.Num::money(Num::neg($r['owed'])))->implode('; ') ?: 'none');

            foreach (['This month' => [now()->startOfMonth(), now()], 'Last month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()]] as $label => [$from, $to]) {
                $rows = DB::table('journal_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')->join('accounts as a', 'a.id', '=', 'l.account_id')
                    ->join('account_categories as c', 'c.id', '=', 'a.account_category_id')->where('l.is_void', false)->whereIn('c.nature', ['income', 'expense'])->where('e.type', '!=', 'closing')
                    ->whereIn('l.branch_id', $ids)->whereDay('l.date', '>=', $from->toDateString())->whereDay('l.date', '<=', $to->toDateString())
                    ->groupBy('c.id', 'c.name', 'c.nature')->selectRaw('c.name as name, c.nature as nature, SUM(l.base_debit) as debit, SUM(l.base_credit) as credit')->get();
                $totals = ['income' => '0', 'expense' => '0'];
                $parts = [];
                foreach ($rows as $row) {
                    $amount = $row->nature === 'income' ? Num::sub($row->credit, $row->debit) : Num::sub($row->debit, $row->credit);
                    $totals[$row->nature] = Num::add($totals[$row->nature], $amount);
                    if (! Num::isZero(Num::money($amount))) {
                        $parts[] = $row->name.' ('.$row->nature.') '.Num::money($amount);
                    }
                }
                $out[] = $label.' ('.$from->toDateString().' to '.$to->toDateString().'): income '.Num::money($totals['income']).', expenses '.Num::money($totals['expense'])
                    .', profit '.Num::money(Num::sub($totals['income'], $totals['expense'])).($parts ? '. By category: '.implode('; ', array_slice($parts, 0, 25)) : '');
            }

            $today = Voucher::whereNull('cancelled_at')->whereDay('date', today_iso())->selectRaw('kind, COUNT(*) as papers, SUM(base_amount) as total')->groupBy('kind')->get()
                ->map(fn ($row) => Voucher::KINDS[$row->kind][0].' '.$row->papers.' ('.Num::money($row->total).')')->implode('; ');
            $out[] = 'Papers recorded today: '.($today ?: 'none');
            $due = Voucher::with(['party', 'currency'])->where('kind', Voucher::MONEY_IN)->whereNull('cancelled_at')->whereDay('due_date', '>=', today_iso())->whereDay('due_date', '<=', now()->addDays(7)->toDateString())->orderBy('due_date')->limit(15)->get()
                ->map(fn (Voucher $v) => $v->due_date?->toDateString().' '.$v->party?->name.' '.Num::money($v->amount).' '.$v->currency?->code)->implode('; ');
            $out[] = 'To be paid back in the next 7 days: '.($due ?: 'nothing');

            return mb_substr(implode("\n", $out), 0, 9000);
        };

        return $all ? Branch::everywhere($work) : $work();
    }
}
