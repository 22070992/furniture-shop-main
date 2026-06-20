<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVisualFeedbackCommentRequest;
use App\Http\Requests\StoreVisualFeedbackRequest;
use App\Models\VisualFeedback;
use App\Models\VisualFeedbackAttachment;
use App\Services\VisualFeedbackFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class VisualFeedbackController extends Controller
{
    public function store(StoreVisualFeedbackRequest $request, VisualFeedbackFileService $files)
    {
        $user = $request->user();
        $data = $request->validated();
        $screenshotData = $data['screenshot_data'] ?? null;
        $attachment = $request->file('attachment');

        unset($data['screenshot_data'], $data['attachment']);

        $pagePath = $data['page_path'] ?? parse_url($data['page_url'], PHP_URL_PATH) ?: '/';
        $data['page_path'] = $pagePath;
        $data['route_name'] = $this->resolveRouteName($pagePath) ?: ($data['route_name'] ?? null);
        $data['user_id'] = $user->getKey();
        $data['status'] = 'Open';
        $data['created_by_role'] = $this->userRoleLabel($user);

        $feedback = VisualFeedback::create($data);

        if ($path = $files->storeScreenshotData($screenshotData, $feedback)) {
            $feedback->update(['screenshot_path' => $path]);
        }

        $files->storeAttachment($attachment, $feedback, $user);

        $feedback->activities()->create([
            'user_id' => $user->getKey(),
            'action' => 'created',
            'new_value' => [
                'status' => $feedback->status,
                'type' => $feedback->type,
                'priority' => $feedback->priority,
            ],
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Cảm ơn bạn. Phản hồi đã được gửi thành công.',
                'feedback_id' => $feedback->id,
                'show_url' => $user->is_staff ? route('admin.feedback.show', $feedback) : route('feedback.show', $feedback),
            ], 201);
        }

        return redirect()
            ->route($user->is_staff ? 'admin.feedback.show' : 'feedback.show', $feedback)
            ->with('success', 'Cảm ơn bạn. Phản hồi đã được gửi thành công. Mã phản hồi: #' . $feedback->id);
    }

    public function myIndex(Request $request)
    {
        $query = VisualFeedback::query()
            ->where('user_id', $request->user()->getKey())
            ->withCount('attachments')
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('q')) {
            $search = '%' . $request->string('q') . '%';
            $query->where(function ($inner) use ($search) {
                $inner->where('title', 'like', $search)
                    ->orWhere('description', 'like', $search)
                    ->orWhere('element_text', 'like', $search);
            });
        }

        return view('feedback.index', [
            'feedbacks' => $query->paginate(12)->withQueryString(),
            'statuses' => VisualFeedback::STATUSES,
        ]);
    }

    public function myShow(VisualFeedback $feedback)
    {
        $this->authorize('view', $feedback);

        $feedback->load([
            'user',
            'assignee',
            'attachments',
            'publicComments.user',
        ]);

        return view('feedback.show', [
            'feedback' => $feedback,
        ]);
    }

    public function comment(
        StoreVisualFeedbackCommentRequest $request,
        VisualFeedback $feedback,
        VisualFeedbackFileService $files
    ) {
        $this->authorize('comment', $feedback);

        $feedback->comments()->create([
            'user_id' => $request->user()->getKey(),
            'comment' => $request->validated()['comment'],
            'is_internal' => false,
        ]);

        $files->storeAttachment($request->file('attachment'), $feedback, $request->user());

        $feedback->activities()->create([
            'user_id' => $request->user()->getKey(),
            'action' => 'commented',
            'new_value' => ['is_internal' => false],
        ]);

        return back()->with('success', 'Bình luận đã được gửi.');
    }

    public function screenshot(VisualFeedback $feedback)
    {
        $this->authorize('view', $feedback);

        abort_unless($feedback->screenshot_path && Storage::disk('local')->exists($feedback->screenshot_path), 404);

        return Storage::disk('local')->response($feedback->screenshot_path, 'feedback-' . $feedback->id . '.png');
    }

    public function attachment(VisualFeedbackAttachment $attachment)
    {
        $this->authorize('view', $attachment->feedback);

        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);

        return Storage::disk('local')->response($attachment->file_path, $attachment->file_name);
    }

    private function resolveRouteName(string $path): ?string
    {
        try {
            $probe = Request::create($path ?: '/', 'GET');
            $route = RouteFacade::getRoutes()->match($probe);

            return $route?->getName();
        } catch (\Throwable) {
            return null;
        }
    }

    private function userRoleLabel($user): string
    {
        if ($user->is_staff) {
            return 'admin';
        }

        return method_exists($user, 'getRoleNames')
            && Schema::hasTable('model_has_roles')
            && $user->getRoleNames()->isNotEmpty()
            ? $user->getRoleNames()->first()
            : 'user';
    }
}
