<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\ComplianceAlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function __construct(
        private ComplianceAlertService $alertService
    ) {}

    // GET /notifications
    public function index()
    {
        $data = $this->alertService->getUnreadNotifications(Auth::id());
        return response()->json(['success' => true, ...$data]);
    }

    // GET /notifications/all
    public function all(Request $request)
    {
        $type = $request->query('type');
        $query = Notification::forUser(Auth::id())
            ->orderByDesc('created_at')
            ->limit(100);

        if ($type) {
            $query->where('notification_type', $type);
        }

        $notifications = $query->get()->map(function (Notification $n) {
            return [
                'id'                => $n->id,
                'notification_type' => $n->notification_type,
                'icon'              => $n->icon,
                'severity'          => $n->severity,
                'title'             => $n->title,
                'message'           => $n->message,
                'data'              => $n->data ?? [],
                'read_at'           => $n->read_at?->toIso8601String(),
                'is_read'           => $n->isRead(),
                'created_at'        => $n->created_at?->toIso8601String(),
                'created_at_human'  => $n->created_at?->diffForHumans(),
            ];
        });


        $unreadCount = Notification::forUser(Auth::id())->unread()->count();

        return response()->json([
            'success'        => true,
            'unread_count'   => $unreadCount,
            'total'          => $notifications->count(),
            'notifications'  => $notifications->values(),
        ]);
    }

    // POST /notifications/{id}/read
    public function markAsRead(int $id)
    {
        $ok = $this->alertService->markAsRead($id, Auth::id());

        return response()->json([
            'success' => $ok,
            'message' => $ok ? 'Notification marked as read.' : 'Notification not found.',
        ], $ok ? 200 : 404);
    }

    // POST /notifications/read-all
    public function markAllAsRead()
    {
        $count = $this->alertService->markAllAsRead(Auth::id());
        return response()->json([
            'success' => true,
            'updated' => $count,
            'message' => "{$count} notification(s) marked as read.",
        ]);
    }
}
