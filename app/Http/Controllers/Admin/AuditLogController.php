<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = AdminAuditLog::with(['admin:id,name', 'targetUser:id,name'])
            ->when($request->action, fn($q, $a) => $q->where('action', $a))
            ->when($request->admin_id, fn($q, $id) => $q->where('admin_id', $id))
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        $admins = User::where('role', 'admin')->orderBy('name')->get(['id', 'name']);
        $actions = ['status_change', 'credit_adjustment', 'post_create', 'post_delete'];

        return view('admin.audit-log.index', compact('logs', 'admins', 'actions'));
    }
}
