<?php

namespace App\Services;

use App\Models\Document;
use App\Models\ReportSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/** Reports that leave by email on their own, and the papers of the vault that are about to expire. */
class Schedules
{
    /** Called every hour: send what is due at this hour, once a day. Returns how many left. */
    public static function tick(): int
    {
        $now = now();
        $sent = 0;
        foreach (ReportSchedule::where('is_active', true)->where('hour', (int) $now->hour)->get() as $schedule) {
            if ($schedule->last_sent_on?->toDateString() === today_iso() || ! self::isDay($schedule, $now)) {
                continue;
            }
            $sent += self::send($schedule) ? 1 : 0;
        }

        return $sent;
    }

    /** Weekly: 1 is Saturday, 7 is Friday. Monthly: the day of the month. */
    public static function isDay(ReportSchedule $schedule, Carbon $now): bool
    {
        return match ($schedule->frequency) {
            'weekly' => (($now->dayOfWeek + 1) % 7) + 1 === $schedule->day,
            'monthly' => (int) $now->day === $schedule->day,
            default => true,
        };
    }

    /** @return array{0: string, 1: string} the dates the workbook covers */
    public static function period(ReportSchedule $schedule): array
    {
        $today = Carbon::parse(today_iso());

        return match ($schedule->frequency) {
            'weekly' => [$today->copy()->subDays(6)->toDateString(), $today->toDateString()],
            'monthly' => [$today->copy()->subMonthNoOverflow()->addDay()->toDateString(), $today->toDateString()],
            default => [$today->toDateString(), $today->toDateString()],
        };
    }

    public static function send(ReportSchedule $schedule): bool
    {
        [$from, $to] = self::period($schedule);
        $modules = ReportSchedule::CONTENTS[$schedule->content][1] ?? array_keys(ExportCenter::MODULES);
        $business = (string) (setting('company_name') ?: tenant()?->name);
        try {
            $workbook = ExportCenter::build($from, $to, $modules);
            $file = (Str::slug($business) ?: 'kasib').'-'.$from.'-'.$to.'.xlsx';
            Mail::raw($business."\n".$schedule->name."\n".fmt_date($from).' – '.fmt_date($to), function ($message) use ($schedule, $business, $workbook, $file) {
                $message->to($schedule->email)->subject($business.' · '.$schedule->name)
                    ->attachData($workbook, $file, ['mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
            });
        } catch (Throwable $e) {
            report($e);

            return false;
        }
        $schedule->update(['last_sent_on' => today_iso()]);

        return true;
    }

    /** Called every night: tell the owner once about each paper of the vault that expires within thirty days. */
    public static function expiring(): int
    {
        $told = 0;
        foreach (Document::whereNull('warned_at')->whereNotNull('expires_on')->whereDayOf('expires_on', '<=', now()->addDays(30)->toDateString())->get() as $document) {
            $document->update(['warned_at' => now()]);
            Alerts::raise('document', $document->title.' · '.fmt_date($document->expires_on), $document, true);
            $told++;
        }

        return $told;
    }
}
