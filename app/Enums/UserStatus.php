<?php

namespace App\Enums;

enum UserStatus: string
{
    case NeedsProfile = 'needs_profile';
    case Active = 'active';              // passengers only
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
}
