<?php

declare(strict_types=1);

namespace App\Enums;

/** Who is requesting lookups. Playbooks are automated service accounts. */
enum UserRole: string
{
    case Analyst = 'analyst';
    case Playbook = 'playbook';
    case Admin = 'admin';
}
