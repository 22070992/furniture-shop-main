<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VisualFeedback;

class VisualFeedbackPolicy
{
    public function create(User $user): bool
    {
        return $user->is_active ?? true;
    }

    public function view(User $user, VisualFeedback $feedback): bool
    {
        return (bool) $user->is_staff || $feedback->isOwnedBy($user);
    }

    public function update(User $user, VisualFeedback $feedback): bool
    {
        return (bool) $user->is_staff;
    }

    public function delete(User $user, VisualFeedback $feedback): bool
    {
        return (bool) $user->is_staff;
    }

    public function comment(User $user, VisualFeedback $feedback): bool
    {
        return (bool) $user->is_staff || $feedback->isOwnedBy($user);
    }

    public function viewInternal(User $user, VisualFeedback $feedback): bool
    {
        return (bool) $user->is_staff;
    }
}
