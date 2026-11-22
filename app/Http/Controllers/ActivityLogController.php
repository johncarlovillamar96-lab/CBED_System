<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\TablePagination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivityLogController extends Controller
{
    public function index(Request $request, MonitoringReportController $monitoring)
    {
        if (! $request->user()->isAdmin()) {
            return $monitoring->activityLogs($request);
        }

        $roles = User::ROLES;
        $actions = ['Sign in', 'Sign out', 'View', 'Download', 'Create', 'Update', 'Delete', 'Submit'];
        $role = $request->query('role');
        $action = $request->query('action');
        $search = $request->query('search');
        $search = is_string($search) ? mb_substr(trim($search), 0, 100) : '';
        $role = is_string($role) && isset($roles[$role]) ? $role : '';
        $action = in_array($action, $actions, true) ? $action : '';
        $filters = array_filter(compact('role', 'action', 'search'));

        $entries = DB::table('activity_logs')
            ->when($role, fn ($query) => $query->where('actor_role', $role))
            ->when($action, fn ($query) => $query->where('action', $action))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('actor_name', 'like', '%'.$search.'%')->orWhere('route', 'like', '%'.$search.'%');
            }))
            ->latest('id')
            ->paginate(TablePagination::size($request))
            ->appends($filters + ['per_page' => TablePagination::size($request)]);

        return view('system-activity', compact('entries', 'roles', 'actions', 'filters'));
    }
}
