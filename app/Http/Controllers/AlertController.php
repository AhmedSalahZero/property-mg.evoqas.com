<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AlertController extends Controller
{
    // ── List alerts ──────────────────────────────────────────────
    public function index(Request $request)
    {
        $authUser = $request->user();

        $alerts = Alert::with('user')
            ->forCompany($authUser->company_id ?? 0)
            ->when($request->type,   fn($q) => $q->ofType($request->type))
            ->when($request->unread === 'true', fn($q) => $q->unread())
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Alerts/Index', [
            'alerts'      => $alerts,
            'filters'     => $request->only(['type', 'unread']),
            'unreadCount' => Alert::forCompany($authUser->company_id ?? 0)->unread()->count(),
            'typeLabels'  => Alert::TYPE_LABELS,
        ]);
    }

    // ── Mark single alert as read ────────────────────────────────
    public function markRead(Request $request, Alert $alert)
    {
        $authUser = $request->user();

        abort_unless(
            $authUser->is_super_admin ||
            $alert->company_id === $authUser->company_id,
            403
        );

        $alert->markAsRead();

        return back()->with('success', 'Alert marked as read.');
    }

    // ── Mark all alerts as read ──────────────────────────────────
    public function markAllRead(Request $request)
    {
        $authUser = $request->user();

        Alert::forCompany($authUser->company_id ?? 0)
            ->unread()
            ->update(['is_read' => true]);

        return back()->with('success', 'All alerts marked as read.');
    }

    // ── Delete alert ─────────────────────────────────────────────
    public function destroy(Request $request, Alert $alert)
    {
        $authUser = $request->user();

        abort_unless(
            $authUser->is_super_admin ||
            ($alert->company_id === $authUser->company_id &&
             in_array($authUser->role, ['company_admin', 'manager'])),
            403
        );

        $alert->delete();

        return back()->with('success', 'Alert deleted.');
    }

    // ── Unread count (for nav badge) ─────────────────────────────
    public function unreadCount(Request $request)
    {
        $authUser = $request->user();

        $count = Alert::forCompany($authUser->company_id ?? 0)
            ->unread()
            ->count();

        return response()->json(['count' => $count]);
    }
}