@extends('layouts.admin')

@section('content')
    @php
        $codexJson = json_encode($codexPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $attributesJson = json_encode($feedback->element_attributes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $viewportJson = json_encode($feedback->viewport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $browserJson = json_encode($feedback->browser_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    @endphp

    <div class="page-header d-print-none" data-feedback-id="admin.feedback.show.header" data-feedback-record-id="{{ $feedback->id }}">
        <div class="container-xl">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <div class="page-pretitle">Visual Feedback</div>
                    <h2 class="page-title">#{{ $feedback->id }} {{ $feedback->title }}</h2>
                </div>
                <div class="col-auto ms-auto d-print-none">
                    <div class="btn-list">
                        <a href="{{ route('admin.feedback.index') }}" class="btn">Back</a>
                        <a href="{{ $feedback->page_url }}" target="_blank" rel="noreferrer" class="btn">Open original page</a>
                        <button type="button" class="btn" data-copy-target="feedback-selector">Copy selector</button>
                        <button type="button" class="btn btn-primary" data-copy-target="feedback-codex-json">Copy for Codex</button>
                        <button type="button" class="btn btn-outline-primary" data-copy-target="feedback-fix-prompt">Copy Fix Prompt</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="page-body">
        <div class="container-xl">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <textarea id="feedback-selector" class="d-none" readonly>{{ $feedback->css_selector }}</textarea>
            <textarea id="feedback-codex-json" class="d-none" readonly>{{ $codexJson }}</textarea>
            <textarea id="feedback-fix-prompt" class="d-none" readonly>{{ $fixPrompt }}</textarea>

            <div class="row row-cards">
                <div class="col-lg-8">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="card-title">Screenshot and selected element</h3>
                        </div>
                        <div class="card-body">
                            @if ($feedback->screenshot_path)
                                <img src="{{ route('feedback.screenshot', $feedback) }}" class="img-fluid rounded border"
                                    alt="Feedback screenshot">
                            @else
                                <div class="alert alert-warning">No screenshot was saved for this feedback.</div>
                            @endif
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="card-title">Problem</h3>
                        </div>
                        <div class="card-body">
                            <dl class="row">
                                <dt class="col-md-3">Description</dt>
                                <dd class="col-md-9">{{ $feedback->description }}</dd>
                                <dt class="col-md-3">Expected result</dt>
                                <dd class="col-md-9">{{ $feedback->expected_result ?: 'Not provided.' }}</dd>
                                <dt class="col-md-3">Element text</dt>
                                <dd class="col-md-9">{{ $feedback->element_text ?: 'Empty' }}</dd>
                                <dt class="col-md-3">CSS selector</dt>
                                <dd class="col-md-9 text-break"><code>{{ $feedback->css_selector }}</code></dd>
                                <dt class="col-md-3">XPath</dt>
                                <dd class="col-md-9 text-break"><code>{{ $feedback->xpath ?: 'Not captured' }}</code></dd>
                            </dl>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="card-title">Comments</h3>
                        </div>
                        <div class="card-body">
                            @forelse ($feedback->comments as $comment)
                                <div class="border-bottom pb-3 mb-3">
                                    <div class="d-flex justify-content-between gap-2">
                                        <div>
                                            <strong>{{ $comment->user?->full_name() ?? 'Unknown' }}</strong>
                                            @if ($comment->is_internal)
                                                <span class="badge bg-yellow-lt ms-2">Internal</span>
                                            @endif
                                        </div>
                                        <span class="text-muted small">{{ $comment->created_at->format('d/m/Y H:i') }}</span>
                                    </div>
                                    <p class="mb-0 mt-2">{{ $comment->comment }}</p>
                                </div>
                            @empty
                                <p class="text-muted">No comments yet.</p>
                            @endforelse

                            <form method="POST" action="{{ route('admin.feedback.comments.store', $feedback) }}"
                                enctype="multipart/form-data">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label">Reply</label>
                                    <textarea name="comment" class="form-control" rows="4" required></textarea>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-7">
                                        <label class="form-label">Attachment</label>
                                        <input type="file" name="attachment" class="form-control"
                                            accept=".jpg,.jpeg,.png,.webp,.pdf,.txt,.doc,.docx">
                                    </div>
                                    <div class="col-md-5 d-flex align-items-end">
                                        <label class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_internal" value="1">
                                            <span class="form-check-label">Internal comment</span>
                                        </label>
                                    </div>
                                </div>
                                <button class="btn btn-primary mt-3" type="submit">Add comment</button>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Timeline</h3>
                        </div>
                        <div class="card-body">
                            @forelse ($feedback->activities as $activity)
                                <div class="border-bottom pb-2 mb-2">
                                    <strong>{{ $activity->action }}</strong>
                                    <span class="text-muted small">by {{ $activity->user?->full_name() ?? 'System' }}
                                        at {{ $activity->created_at->format('d/m/Y H:i') }}</span>
                                    @if ($activity->old_value || $activity->new_value)
                                        <pre class="mt-2 mb-0 p-2 rounded bg-dark text-white">{{ json_encode(['old' => $activity->old_value, 'new' => $activity->new_value], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    @endif
                                </div>
                            @empty
                                <p class="text-muted mb-0">No activity yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <form method="POST" action="{{ route('admin.feedback.update', $feedback) }}" class="card mb-3">
                        @csrf
                        @method('PATCH')
                        <div class="card-header">
                            <h3 class="card-title">Manage feedback</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status }}" @selected($feedback->status === $status)>{{ $status }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Priority</label>
                                <select name="priority" class="form-select">
                                    @foreach ($priorities as $priority)
                                        <option value="{{ $priority }}" @selected($feedback->priority === $priority)>{{ $priority }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Type</label>
                                <select name="type" class="form-select">
                                    @foreach ($types as $type)
                                        <option value="{{ $type }}" @selected($feedback->type === $type)>{{ $type }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Assigned admin</label>
                                <select name="assigned_to" class="form-select">
                                    <option value="">Unassigned</option>
                                    @foreach ($admins as $admin)
                                        <option value="{{ $admin->user_id }}" @selected((int) $feedback->assigned_to === (int) $admin->user_id)>
                                            {{ $admin->full_name() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Admin note</label>
                                <textarea name="admin_note" class="form-control" rows="5">{{ $feedback->admin_note }}</textarea>
                            </div>
                            <button class="btn btn-primary w-100" type="submit">Save changes</button>
                        </div>
                    </form>

                    <div class="card mb-3">
                        <div class="card-body d-flex gap-2">
                            <form method="POST" action="{{ route('admin.feedback.update', $feedback) }}" class="flex-fill">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="Resolved">
                                <button class="btn btn-success w-100" type="submit">Mark Resolved</button>
                            </form>
                            <form method="POST" action="{{ route('admin.feedback.update', $feedback) }}" class="flex-fill">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="Open">
                                <button class="btn btn-warning w-100" type="submit">Reopen</button>
                            </form>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="card-title">Metadata</h3>
                        </div>
                        <div class="card-body">
                            <dl class="row">
                                <dt class="col-5">URL</dt>
                                <dd class="col-7 text-break">{{ $feedback->page_url }}</dd>
                                <dt class="col-5">Path</dt>
                                <dd class="col-7">{{ $feedback->page_path }}</dd>
                                <dt class="col-5">Route</dt>
                                <dd class="col-7">{{ $feedback->route_name ?: 'Unknown' }}</dd>
                                <dt class="col-5">Device</dt>
                                <dd class="col-7">{{ $feedback->device_type ?: 'Unknown' }}</dd>
                                <dt class="col-5">Sender</dt>
                                <dd class="col-7">{{ $feedback->user?->full_name() ?? 'Unknown' }} ({{ $feedback->created_by_role }})</dd>
                                <dt class="col-5">Created</dt>
                                <dd class="col-7">{{ $feedback->created_at->format('d/m/Y H:i') }}</dd>
                            </dl>
                            <h4>Element attributes</h4>
                            <pre class="p-2 rounded bg-dark text-white">{{ $attributesJson }}</pre>
                            <h4>Viewport</h4>
                            <pre class="p-2 rounded bg-dark text-white">{{ $viewportJson }}</pre>
                            <h4>Browser</h4>
                            <pre class="p-2 rounded bg-dark text-white">{{ $browserJson }}</pre>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="card-title">Attachments</h3>
                        </div>
                        <div class="card-body">
                            @forelse ($feedback->attachments as $attachment)
                                <a class="d-block mb-2" href="{{ route('feedback.attachment', $attachment) }}">
                                    {{ $attachment->file_name }}
                                    <span class="text-muted small">({{ number_format($attachment->file_size / 1024, 1) }} KB)</span>
                                </a>
                            @empty
                                <p class="text-muted mb-0">No attachments.</p>
                            @endforelse
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.feedback.destroy', $feedback) }}"
                        onsubmit="return confirm('Delete this feedback permanently?')" class="card">
                        @csrf
                        @method('DELETE')
                        <div class="card-body">
                            <button type="submit" class="btn btn-danger w-100">Delete feedback</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('[data-copy-target]').forEach((button) => {
            button.addEventListener('click', async () => {
                const target = document.getElementById(button.dataset.copyTarget);
                if (!target) return;
                await navigator.clipboard.writeText(target.value || target.textContent);
                const original = button.textContent;
                button.textContent = 'Copied';
                setTimeout(() => {
                    button.textContent = original;
                }, 1200);
            });
        });
    </script>
@endsection
