<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = Activity::with(['subject', 'causer'])
            ->latest();

        if ($log = $request->input('log')) {
            $query->where('log_name', $log);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('subject_type', 'like', "%{$search}%");
            });
        }

        $logs        = $query->paginate(50)->withQueryString();
        $logChannels = Activity::distinct()->pluck('log_name')->filter()->sort()->values();

        return view('logs.index', compact('logs', 'logChannels'));
    }
}
