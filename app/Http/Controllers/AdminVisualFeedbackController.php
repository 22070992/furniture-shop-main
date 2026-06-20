<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVisualFeedbackCommentRequest;
use App\Http\Requests\UpdateVisualFeedbackRequest;
use App\Models\User;
use App\Models\VisualFeedback;
use App\Services\VisualFeedbackFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AdminVisualFeedbackController extends Controller
{
    public function index(Request $request)
    {
        $query = VisualFeedback::query()
            ->with(['user', 'assignee'])
            ->withCount('attachments')
            ->latest();

        foreach (['status', 'priority', 'type', 'assigned_to', 'user_id', 'page_path'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        if ($request->boolean('has_screenshot')) {
            $query->whereNotNull('screenshot_path');
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to'));
        }

        if ($request->filled('q')) {
            $search = '%' . $request->string('q') . '%';
            $query->where(function ($inner) use ($search) {
                $inner->where('title', 'like', $search)
                    ->orWhere('description', 'like', $search)
                    ->orWhere('element_text', 'like', $search)
                    ->orWhere('css_selector', 'like', $search);
            });
        }

        return view('admin.feedback.index', [
            'feedbacks' => $query->paginate(15)->withQueryString(),
            'statuses' => VisualFeedback::STATUSES,
            'priorities' => VisualFeedback::PRIORITIES,
            'types' => VisualFeedback::TYPES,
            'admins' => User::where('is_staff', true)->orderBy('first_name')->get(),
            'submitters' => User::whereIn('user_id', VisualFeedback::query()->select('user_id')->distinct())
                ->orderBy('first_name')
                ->get(),
            'stats' => $this->stats(),
            'typeStats' => VisualFeedback::selectRaw('type, COUNT(*) as total')->groupBy('type')->orderByDesc('total')->get(),
            'pageStats' => VisualFeedback::selectRaw('page_path, COUNT(*) as total')->groupBy('page_path')->orderByDesc('total')->limit(8)->get(),
        ]);
    }

    public function show(VisualFeedback $feedback)
    {
        $this->authorize('view', $feedback);

        $feedback->load([
            'user',
            'assignee',
            'attachments.user',
            'comments.user',
            'activities.user',
        ]);

        return view('admin.feedback.show', [
            'feedback' => $feedback,
            'statuses' => VisualFeedback::STATUSES,
            'priorities' => VisualFeedback::PRIORITIES,
            'types' => VisualFeedback::TYPES,
            'admins' => User::where('is_staff', true)->orderBy('first_name')->get(),
            'codexPayload' => $this->codexPayload($feedback),
            'fixPrompt' => $this->fixPrompt($feedback),
        ]);
    }

    public function update(UpdateVisualFeedbackRequest $request, VisualFeedback $feedback)
    {
        $data = $request->validated();
        $tracked = ['status', 'priority', 'assigned_to', 'admin_note', 'type', 'parent_id'];
        $changes = [];

        foreach ($tracked as $field) {
            if (array_key_exists($field, $data) && $feedback->{$field} != $data[$field]) {
                $changes[$field] = [
                    'old' => $feedback->{$field},
                    'new' => $data[$field],
                ];
                $feedback->{$field} = $data[$field];
            }
        }

        if (isset($changes['status'])) {
            $feedback->resolved_at = in_array($feedback->status, ['Resolved', 'Closed'], true) ? now() : null;
        }

        $feedback->save();

        foreach ($changes as $field => $change) {
            $feedback->activities()->create([
                'user_id' => $request->user()->getKey(),
                'action' => 'updated_' . $field,
                'old_value' => [$field => $change['old']],
                'new_value' => [$field => $change['new']],
            ]);
        }

        if ($changes !== []) {
            Log::info('Visual feedback updated', [
                'feedback_id' => $feedback->id,
                'admin_id' => $request->user()->getKey(),
                'changes' => $changes,
            ]);
        }

        return back()->with('success', 'Visual Feedback đã được cập nhật.');
    }

    public function comment(
        StoreVisualFeedbackCommentRequest $request,
        VisualFeedback $feedback,
        VisualFeedbackFileService $files
    ) {
        $feedback->comments()->create([
            'user_id' => $request->user()->getKey(),
            'comment' => $request->validated()['comment'],
            'is_internal' => $request->boolean('is_internal'),
        ]);

        $files->storeAttachment($request->file('attachment'), $feedback, $request->user());

        $feedback->activities()->create([
            'user_id' => $request->user()->getKey(),
            'action' => $request->boolean('is_internal') ? 'internal_comment' : 'commented',
            'new_value' => ['is_internal' => $request->boolean('is_internal')],
        ]);

        return back()->with('success', 'Phản hồi đã được thêm.');
    }

    public function destroy(Request $request, VisualFeedback $feedback)
    {
        $this->authorize('delete', $feedback);

        Storage::disk('local')->deleteDirectory('visual-feedback/' . $feedback->id);
        $feedback->delete();

        Log::info('Visual feedback deleted', [
            'feedback_id' => $feedback->id,
            'admin_id' => $request->user()->getKey(),
        ]);

        return redirect()->route('admin.feedback.index')->with('success', 'Visual Feedback đã được xóa.');
    }

    private function stats(): array
    {
        return [
            'total' => VisualFeedback::count(),
            'open' => VisualFeedback::where('status', 'Open')->count(),
            'in_progress' => VisualFeedback::where('status', 'In Progress')->count(),
            'critical' => VisualFeedback::where('priority', 'Critical')->count(),
            'resolved' => VisualFeedback::whereIn('status', ['Resolved', 'Closed'])->count(),
        ];
    }

    private function codexPayload(VisualFeedback $feedback): array
    {
        return [
            'feedback_id' => $feedback->id,
            'page_url' => $feedback->page_url,
            'page_path' => $feedback->page_path,
            'page_title' => $feedback->page_title,
            'route_name' => $feedback->route_name,
            'type' => $feedback->type,
            'priority' => $feedback->priority,
            'status' => $feedback->status,
            'title' => $feedback->title,
            'description' => $feedback->description,
            'expected_result' => $feedback->expected_result,
            'element' => [
                'tag' => $feedback->element_tag,
                'text' => $feedback->element_text,
                'id' => $feedback->element_id,
                'classes' => preg_split('/\s+/', (string) $feedback->element_classes, -1, PREG_SPLIT_NO_EMPTY),
                'css_selector' => $feedback->css_selector,
                'xpath' => $feedback->xpath,
                'attributes' => $feedback->element_attributes,
                'rect' => $feedback->element_rect,
            ],
            'viewport' => $feedback->viewport,
            'scroll_position' => $feedback->scroll_position,
            'browser' => $feedback->browser_info,
            'device_type' => $feedback->device_type,
            'screenshot' => $feedback->screenshot_path ? route('feedback.screenshot', $feedback) : null,
            'submitted_by' => [
                'user_id' => $feedback->user_id,
                'role' => $feedback->created_by_role,
                'name' => $feedback->user?->full_name(),
            ],
        ];
    }

    private function fixPrompt(VisualFeedback $feedback): string
    {
        return "Hãy kiểm tra feedback ID #{$feedback->id}.\n\n"
            . "Trang bị lỗi:\n{$feedback->page_url}\n\n"
            . "Element:\n{$feedback->css_selector}\n\n"
            . "Vấn đề:\n{$feedback->description}\n\n"
            . "Kết quả mong muốn:\n" . ($feedback->expected_result ?: 'Chưa cung cấp.') . "\n\n"
            . "Hãy:\n"
            . "1. Xác định view/component và source code tạo ra element này.\n"
            . "2. Tìm nguyên nhân gốc.\n"
            . "3. Chỉnh sửa tối thiểu các file cần thiết.\n"
            . "4. Không ảnh hưởng các chức năng khác.\n"
            . "5. Chạy test phù hợp.\n"
            . "6. Mở lại trang và xác minh lỗi đã được sửa.\n"
            . "7. Liệt kê các file đã thay đổi.";
    }
}
