<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    /**
     * Get all notifications for the authenticated user
     * 
     * GET /api/notifications
     */
    public function index(Request $request)
    {
        $companyId = $this->getCompanyId();
        $user = auth()->user();

        $query = Notification::where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        // Filter by read status
        if ($request->has('is_read')) {
            $query->where('is_read', $request->boolean('is_read'));
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        $notifications = $query->paginate($request->input('limit', 20));

        return $this->paginated($notifications->items(), [
            'page' => $notifications->currentPage(),
            'limit' => $notifications->perPage(),
            'total' => $notifications->total(),
            'totalPages' => $notifications->lastPage(),
        ]);
    }

    /**
     * Get unread notification count
     * 
     * GET /api/notifications/unread-count
     */
    public function unreadCount(Request $request)
    {
        $companyId = $this->getCompanyId();
        $user = auth()->user();

        $count = Notification::where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return $this->success([
            'count' => $count,
        ], 'Unread notification count retrieved');
    }

    /**
     * Mark notification as read
     * 
     * PUT /api/notifications/{id}/read
     */
    public function markAsRead(Request $request, string $id)
    {
        $companyId = $this->getCompanyId();
        $user = auth()->user();

        $notification = Notification::where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->findOrFail($id);

        $notification->markAsRead();

        return $this->success($notification->toArray(), 'Notification marked as read');
    }

    /**
     * Mark all notifications as read
     * 
     * PUT /api/notifications/mark-all-read
     */
    public function markAllAsRead(Request $request)
    {
        $companyId = $this->getCompanyId();
        $user = auth()->user();

        $updated = Notification::where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return $this->success([
            'updated_count' => $updated,
        ], 'All notifications marked as read');
    }

    /**
     * Delete notification
     * 
     * DELETE /api/notifications/{id}
     */
    public function destroy(Request $request, string $id)
    {
        $companyId = $this->getCompanyId();
        $user = auth()->user();

        $notification = Notification::where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->findOrFail($id);

        $notification->delete();

        return $this->success(null, 'Notification deleted successfully');
    }
}
