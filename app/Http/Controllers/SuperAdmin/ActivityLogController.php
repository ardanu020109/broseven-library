<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class ActivityLogController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): View
    {
        Gate::authorize('manage-library');

        $term = trim(Str::limit($request->string('q')->toString(), 100, ''));
        $action = Str::limit($request->string('action')->toString(), 50, '');
        $userId = max(0, $request->integer('user'));
        $from = $this->dateParam($request->string('from')->toString());
        $to = $this->dateParam($request->string('to')->toString());

        $logs = ActivityLog::query()
            ->with('user:id,name,role')
            ->when($term !== '', fn ($q) => $q->where(function ($w) use ($term) {
                $w->where('description', 'like', "%{$term}%")->orWhere('action', 'like', "%{$term}%");
            }))
            ->when($action !== '', fn ($q) => $q->where('action', $action))
            ->when($userId > 0, fn ($q) => $q->where('user_id', $userId))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to->copy()->endOfDay()))
            ->latest()->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('super-admin.activity-logs.index', [
            'logs' => $logs,
            'actions' => ActivityLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
            'users' => User::query()
                ->whereIn('id', ActivityLog::query()->whereNotNull('user_id')->select('user_id')->distinct())
                ->orderBy('name')->pluck('name', 'id'),
            'filters' => [
                'q' => $term,
                'action' => $action,
                'user' => $userId,
                'from' => $from?->toDateString() ?? '',
                'to' => $to?->toDateString() ?? '',
            ],
            'hasFilters' => $term !== '' || $action !== '' || $userId > 0 || $from !== null || $to !== null,
        ]);
    }

    /** Tanggal tidak valid diabaikan, bukan menyebabkan error. */
    private function dateParam(string $value): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
